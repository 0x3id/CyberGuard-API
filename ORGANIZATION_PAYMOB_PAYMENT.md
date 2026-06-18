# Organization Paymob Payment Implementation

## Overview
This implementation adds **Paymob as the payment method for organizations**, replacing the mocked Stripe integration. Organizations can now process payments through Paymob (Egypt's local payment gateway) using the existing Paymob configuration.

## What Changed

### 1. Database Schema (Migration)
**File:** `database/migrations/2026_06_10_add_paymob_payment_to_organizations.php`

New columns added to `organization_subscriptions` table:
- `payment_method` (enum: 'paymob', 'stripe') - Defaults to 'paymob'
- `paymob_order_id` - Stores Paymob order ID
- `paymob_transaction_id` - Stores Paymob transaction ID
- `merchant_reference` - Unique merchant reference (UUID)
- `last_paymob_payload` - Stores last webhook payload (JSON)
- `paid_at` - Timestamp when payment was completed
- `failure_reason` - Payment failure reason if any

### 2. Model Updates
**File:** `app/Models/OrganizationSubscription.php`

Updated `fillable` and `casts` arrays to include the new payment fields:
```php
protected $fillable = [
    // ... existing fields ...
    'payment_method',
    'paymob_order_id',
    'paymob_transaction_id',
    'merchant_reference',
    'last_paymob_payload',
    'paid_at',
    'failure_reason',
];

protected $casts = [
    // ... existing casts ...
    'paid_at' => 'datetime',
    'last_paymob_payload' => 'array',
];
```

### 3. New Controllers

#### OrganizationPaymentController
**File:** `app/Http/Controllers/OrganizationPaymentController.php`

Handles organization payment checkout and status retrieval:

**Endpoints:**
- `POST /api/organizations/{organization_id}/payment/checkout`
  - Initiates Paymob checkout for organization subscription
  - Accepts billing data and plan selection
  - Returns iframe URL for payment form
  
- `GET /api/organizations/{organization_id}/payment/status`
  - Get current payment status for an organization subscription

**Features:**
- Validates organization ownership/membership
- Retrieves plan pricing from config
- Registers order with Paymob API
- Creates payment key for iframe
- Stores merchant reference for webhook verification

#### OrganizationPaymobWebhookController
**File:** `app/Http/Controllers/OrganizationPaymobWebhookController.php`

Handles incoming Paymob webhook callbacks for organization payments:

**Webhook:** `POST /api/billing/paymob/organization-webhook`

**Security Features:**
- HMAC signature verification
- Pessimistic row locking to prevent race conditions
- Idempotency checks (prevents duplicate payment processing)

**Payment Processing:**
1. Verifies webhook signature using Paymob HMAC secret
2. Matches Paymob order ID or merchant reference
3. Locks subscription row for atomic update
4. On successful payment:
   - Updates subscription status to 'active'
   - Sets expiration date based on billing period
   - Records transaction ID and payment timestamp
5. On failed payment:
   - Updates status to 'failed'
   - Logs failure reason

### 4. Updated Controllers

#### OrganizationOnboardingController
**File:** `app/Http/Controllers/OrganizationOnboardingController.php`

**Changes:**
- Creates subscription with `payment_method: 'paymob'` (instead of 'cancelled')
- Sets initial status to `'pending'` (instead of 'cancelled')
- Response now directs users to use the OrganizationPaymentController for checkout
- Removed Stripe mock URL generation

**Flow:**
1. User calls `/api/organizations/initiate` with organization details and plan
2. Organization and subscription created in pending state
3. Response includes `organization_id` for next step
4. User uses this ID to call `/api/organizations/{org_id}/payment/checkout`

### 5. Routes
**File:** `routes/api.php`

**New Routes:**
```php
// Public webhook
Route::post('billing/paymob/organization-webhook', OrganizationPaymobWebhookController::class);

// Authenticated routes
Route::post('/organizations/{organization_id}/payment/checkout', [OrganizationPaymentController::class, 'initiateCheckout']);
Route::get('/organizations/{organization_id}/payment/status', [OrganizationPaymentController::class, 'getPaymentStatus']);
```

## Payment Flow

### Step 1: Organization Initialization
```bash
POST /api/organizations/initiate
Content-Type: application/json
Authorization: Bearer {token}

{
  "org_name": "Acme Corporation",
  "company_domain": "acme.com",
  "company_email": "admin@acme.com",
  "plan": "pro"
}
```

**Response:**
```json
{
  "status": "success",
  "message": "Organization created in pending state. Proceed to payment...",
  "organization_id": "550e8400-e29b-41d4-a716-446655440000",
  "plan": "pro",
  "payment_method": "paymob"
}
```

### Step 2: Initialize Paymob Checkout
```bash
POST /api/organizations/{organization_id}/payment/checkout
Content-Type: application/json
Authorization: Bearer {token}

{
  "organization_id": "550e8400-e29b-41d4-a716-446655440000",
  "plan": "pro",
  "billing_data": {
    "first_name": "Ahmed",
    "last_name": "Hassan",
    "email": "ahmed@acme.com",
    "phone_number": "+20100123456",
    "city": "Cairo",
    "country": "EG",
    "street": "123 Main St",
    "building": "A",
    "floor": "2",
    "apartment": "201",
    "postal_code": "11511"
  }
}
```

**Response:**
```json
{
  "status": "success",
  "iframe_url": "https://accept.paymob.com/api/acceptance/iframes/...",
  "merchant_reference": "550e8400-e29b-41d4-a716-446655440001"
}
```

### Step 3: User Completes Payment
- Frontend embeds the iframe URL and user completes payment
- Paymob processes payment and sends webhook

### Step 4: Paymob Webhook Processing
```
POST /api/billing/paymob/organization-webhook
Header: x-hmac-signature: {...}

{
  "order": {
    "id": 123456,
    "merchant_reference": "550e8400-e29b-41d4-a716-446655440001"
  },
  "transaction": {
    "id": 789012,
    "success": true
  }
}
```

**Automatic Processing:**
- Webhook handler validates signature
- Finds matching subscription by order/merchant reference
- Updates subscription to active status
- Sets expiration date (based on PAYMOB_BILLING_PERIOD_MONTHS config)

### Step 5: Check Payment Status
```bash
GET /api/organizations/{organization_id}/payment/status
Authorization: Bearer {token}
```

**Response:**
```json
{
  "status": "success",
  "payment_status": "active",
  "payment_method": "paymob",
  "plan": "pro",
  "paid_at": "2026-06-10T14:30:00Z",
  "expires_at": "2026-07-10T14:30:00Z"
}
```

## Configuration

Paymob configuration is read from environment variables (existing setup):

```env
PAYMOB_BASE_URL=https://accept.paymob.com/api
PAYMOB_API_KEY=your_api_key
PAYMOB_INTEGRATION_ID=your_integration_id
PAYMOB_IFRAME_ID=your_iframe_id
PAYMOB_HMAC_SECRET=your_hmac_secret
PAYMOB_BILLING_PERIOD_MONTHS=1
```

Organization subscription plans and pricing should be configured in `config/org_subscriptions.php`:

```php
'plans' => [
    'starter' => [
        'max_projects' => 10,
        'max_targets_per_project' => 50,
        'max_members' => 5,
        'max_scans_per_month' => 100,
        'amount_egp' => 500,  // Price in EGP
    ],
    'pro' => [
        'max_projects' => 50,
        'max_targets_per_project' => 200,
        'max_members' => 20,
        'max_scans_per_month' => 500,
        'amount_egp' => 1500,
    ],
    // ...
]
```

## Security Features

1. **HMAC Verification**: All webhooks are validated using Paymob HMAC secret
2. **Row Locking**: Database transactions use pessimistic locking to prevent race conditions
3. **Idempotency**: Multiple webhook calls for the same payment are safely handled
4. **Access Control**: Payment endpoints verify user is organization owner or member
5. **Atomic Updates**: Payment processing is wrapped in database transactions
6. **Merchant Reference**: UUID-based unique identifiers prevent order confusion

## Running the Migration

```bash
# Run the migration
php artisan migrate

# Or rollback if needed
php artisan migrate:rollback --step=1
```

## Testing

### Test Successful Payment
1. Complete organization initialization
2. Call checkout endpoint with valid billing data
3. Simulate Paymob webhook with success:true

### Test Failed Payment
1. Complete organization initialization
2. Call checkout endpoint
3. Simulate Paymob webhook with success:false

### Test Webhook Verification
- Ensure x-hmac-signature header matches payload
- Test with invalid signature (should be rejected)

## Error Handling

The implementation includes comprehensive error handling:

- **422 Unprocessable Entity**: Invalid plan, domain mismatch, or validation errors
- **403 Forbidden**: User not authorized for organization
- **404 Not Found**: Organization not found
- **500 Internal Server Error**: API errors, configuration issues
- **401 Unauthorized**: Invalid HMAC signature on webhook

## Migration Rollback

If needed to revert to Stripe:

```bash
php artisan migrate:rollback --step=1
```

This will remove all Paymob-related columns from the organization_subscriptions table.

## Support

For issues or questions:
1. Check Paymob configuration in `.env`
2. Verify webhook URL is accessible from Paymob servers
3. Monitor logs in `storage/logs/laravel.log`
4. Check subscription status at `/api/organizations/{org_id}/payment/status`
