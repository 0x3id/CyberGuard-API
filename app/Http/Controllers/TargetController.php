<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\Target;
use App\Models\ProjectCollaborator;
use App\Models\AuditLog;
use App\Models\Finding;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use App\Models\Organization;
use App\Support\WorkspaceContext;

class TargetController extends Controller
{
    // ────────────────────────────────────────────
    // POST /api/projects/{project}/targets
    // Add New Target To Project
    // ────────────────────────────────────────────
    public function addNewTarget(Request $request, Project $project) : JsonResponse
    {
        $user = $request->user();
        Gate::authorize('create', [Project::class, $project]);

        $validated = $request->validate([
            'type'        => 'required|in:domain,ip,network',
            'label'       => 'required|string',
            'value' => [
                    'required',
                    Rule::when($request->type === 'ip', ['ip']),
                    Rule::when($request->type === 'domain', ['regex:/^(?!:\/\/)(?=.{1,255}$)((.{1,63}\.){1,127}(?![0-9]*$)[a-z0-9-]+\.?)$/i']), 
                    Rule::when($request->type === 'network', ['regex:/^([0-9]{1,3}\.){3}[0-9]{1,3}\/[0-9]{1,2}$/']),
            ],
        ]);

        $isOrgContext = WorkspaceContext::isOrganization($request);
        $verificationToken = $isOrgContext
            ? 'cyberguard_secret_key:'.Str::random(1024)
            : null;

        $target = Target::create([
            'project_id' => $project->id,
            'type'       => $validated['type'],
            'value'      => $validated['value'],
            'label'      => $validated['label'],
            'is_verified' => ! $isOrgContext,
            'ownership_verification_token' => $verificationToken,
            'risk_score' => 0.00
        ]);

        AuditLog::create([
            'user_id'     => $user->id,
            'owner_type'  => $project->owner_type,
            'owner_id'    => $project->owner_id,
            'action'      => 'target.create',
            'entity_type' => Target::class,
            'entity_id'   => $target->id,
            'ip_address'  => $request->ip(),
            'created_at'  => now(),
        ]);

        return response()->json([
            'status'      => 'success',
            'message'     => 'Target added successfuly',
            'target'      => $target,
            'dns_verification' => $verificationToken ? [
                'record_type' => 'TXT',
                'record_name' => $this->baseDomain($target->value),
                'record_value' => $verificationToken,
            ] : null,
        ], 201);
    }

    // ────────────────────────────────────────────
    // GET /api/targets/{target}
    // Get Target Details
    // ────────────────────────────────────────────
    public function getTargetDetails(Request $request, Target $target) : JsonResponse
    {
        $project = Project::findOrFail($target->project_id);
        $user = $request->user();

        if (!$project->hasAccess($user->id)) {
            return response()->json(['status' => 'error' ,'message' => 'Unauthorized'], 403);
        }

        $this->calcRiskScore($target);
        return response()->json(['status' => 'success' ,'target' => $target]);

    }

    // ────────────────────────────────────────────
    // Get /api/targets
    // Get all targets of user
    // ────────────────────────────────────────────
    public function allTargets(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($request->attributes->get('is_organization_context')) {
            $organization = $request->attributes->get('organization');
            $targets = Target::whereHas('project', function ($query) use ($organization) {
                $query->where('owner_type', \App\Models\Organization::class)
                        ->where('owner_id', $organization->id);
            })->get();
        } else {
            $targets = Target::whereHas('project', function ($query) use ($user) {
                $query->where(function ($nested) use ($user) {
                    $nested->where('owner_type', \App\Models\User::class)
                        ->where('owner_id', $user->id);
                })->orWhereHas('collaborators', function ($q) use ($user) {
                        $q->where('user_id', $user->id)
                            ->where('role', 'editor');
                });
            })->get();
        }

        // Calc Risk Score Of All Targets
        foreach($targets as $target):
            $this->calcRiskScore($target);
        endforeach;

        return response()->json([
            'status' => 'Success',
            'targets' => $targets
        ]);
    }

    // ────────────────────────────────────────────
    // Get /api/projects/{project}/targets
    // Get all targets in project
    // ────────────────────────────────────────────
    public function getAllTargets(Request $request, Project $project) : JsonResponse
    {
        // 1. Check if user have access in this project
        if (!$project->hasAccess($request->user()->id)) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        // 2. Get all targets
        $target = Target::where('project_id', $project->id)->get();
        return response()->json(['status' => 'Success' ,'targets' => $target], 200);
    }

    // ────────────────────────────────────────────
    // PATCH /api/projects/{project}/targets/{target}
    // Get all targets in project
    // ────────────────────────────────────────────
    public function updateTarget(Request $request, Project $project, Target $target) : JsonResponse
    {
        $user = $request->user();
        Gate::authorize('manage', $target);

        if ($target->project_id !== $project->id) {
            return response()->json(['status' => 'Error' ,'message' => 'Target not found in this project'], 404);
        }

        $validated = $request->validate([
            'label'        => 'sometimes|string|max:255',
            'value' => [
                    'sometimes',
                    Rule::when($target->type === 'ip', ['ip']),
                    Rule::when($target->type === 'domain', ['regex:/^(?!:\/\/)(?=.{1,255}$)((.{1,63}\.){1,127}(?![0-9]*$)[a-z0-9-]+\.?)$/i']), 
                    Rule::when($target->type === 'network', ['regex:/^([0-9]{1,3}\.){3}[0-9]{1,3}\/[0-9]{1,2}$/']),
            ],
        ]);

        if (array_key_exists('value', $validated) && WorkspaceContext::isOrganization($request)) {
            $validated['is_verified'] = false;
            $validated['dns_verified_at'] = null;
            $validated['ownership_verification_token'] = 'cyberguard_secret_key:'.Str::random(1024);
        }

        $target->update($validated);

        AuditLog::create([
            'user_id'     => $user->id,
            'owner_type'  => $project->owner_type,
            'owner_id'    => $project->owner_id,
            'action'      => 'target.update',
            'entity_type' => Target::class,
            'entity_id'   => $target->id,
            'ip_address'  => $request->ip(),
            'created_at'  => now(),
        ]);

        return response()->json([
            'status'      => 'Success',
            'message'     => 'Target updated successfuly',
            'target'      => $target,
        ], 200);
    }


    // ────────────────────────────────────────────
    // DELETE /api/projects/{project}/targets/{target}
    // Delete Specefic Target
    // ────────────────────────────────────────────
    public function deleteTarget(Request $request, Project $project, Target $target): JsonResponse
    {
        $user = $request->user();
        Gate::authorize('manage', $target);

        if ($target->project_id !== $project->id) {
            return response()->json(['status' => 'Error' ,'message' => 'Target not found in this project'], 404);
        }

        $target->delete();

        AuditLog::create([
            'user_id'     => $user->id,
            'owner_type'  => $project->owner_type,
            'owner_id'    => $project->owner_id,
            'action'      => 'target.deleted',
            'entity_type' => Target::class,
            'entity_id'   => $target->id,
            'ip_address'  => $request->ip(),
            'created_at'  => now(),
        ]);

        return response()->json(['status' => 'Success' ,'message' => 'Target deleted successfuly'], 200);
    }

    public function verifyDns(Request $request, Target $target): JsonResponse
    {
        Gate::authorize('manage', $target);

        if ($target->type !== 'domain') {
            return response()->json([
                'status' => 'error',
                'message' => 'DNS verification is only available for domain targets.',
            ], 422);
        }

        if (! $target->ownership_verification_token) {
            return response()->json([
                'status' => 'error',
                'message' => 'This target does not have a DNS verification token.',
            ], 422);
        }

        $baseDomain = $this->baseDomain($target->value);
        $records = dns_get_record($target->value, DNS_TXT);
        $txtValues = collect($records ?: [])
            ->pluck('txt')
            ->filter(fn ($value) => is_string($value))
            ->map(fn ($value) => trim($value, "\" \t\n\r\0\x0B"))
            ->values();

        $isVerified = $txtValues->contains($target->ownership_verification_token);

        $target->update([
            'is_verified' => $isVerified,
            'dns_verified_at' => $isVerified ? now() : null,
        ]);

        return response()->json([
            'status' => $isVerified ? 'success' : 'error',
            'message' => $isVerified ? 'DNS ownership verified.' : 'DNS TXT record not found or token mismatch.',
            'target' => $target->fresh(),
            'expected_txt' => [
                'record_type' => 'TXT',
                'record_name' => $baseDomain,
                'record_value' => $target->ownership_verification_token,
            ],
        ], $isVerified ? 200 : 422);
    }

    /**
     * Calc Risk Score Of Target based on formula
     * Score = (Critical * 10 + high * 7 + medium * 4 + low * 1) / 100
     */
    public function calcRiskScore(Target $target) : void
    {
        $findings = Finding::where('target_id' , $target->id)->get();
        if (!$findings) return ;

        $critical = $findings->where('severity' , 'critical')->count();
        $high = $findings->where('severity' , 'high')->count();
        $medium = $findings->where('severity' , 'medium')->count();
        $low = $findings->where('severity' , 'low')->count();

        $riskScore = (($critical*10) + ($high*7) + ($medium*4) + ($low*1)) / 100;

        $target->risk_score = $riskScore;
        $target->save();

        return;
    }

    private function baseDomain(string $value): string
    {
        $host = parse_url(str_contains($value, '://') ? $value : 'https://'.$value, PHP_URL_HOST) ?: $value;
        $host = strtolower(trim($host, ". \t\n\r\0\x0B"));
        $labels = array_values(array_filter(explode('.', $host)));

        if (count($labels) <= 2) {
            return $host;
        }

        $secondLevelTlds = ['co', 'com', 'net', 'org', 'gov', 'edu'];
        $last = $labels[count($labels) - 1];
        $previous = $labels[count($labels) - 2];
        $take = strlen($last) === 2 && in_array($previous, $secondLevelTlds, true) ? 3 : 2;

        return implode('.', array_slice($labels, -$take));
    }
}
