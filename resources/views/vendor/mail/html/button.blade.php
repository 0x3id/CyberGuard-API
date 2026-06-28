<table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="margin:24px 0 8px;">
    <tr>
        <td align="center">
            <table role="presentation" cellspacing="0" cellpadding="0" border="0">
                <tr>
                    <td align="center" height="48" style="height:48px;border-radius:8px;background:#2563eb;">
                        <!--[if mso]>
                        <v:roundrect xmlns:v="urn:schemas-microsoft-com:vml" xmlns:w="urn:schemas-microsoft-com:office:word" href="{{ $url }}" style="height:48px;v-text-anchor:middle;width:{{ ($component === 'table' ? 'auto' : '100%') }};" arcsize="17%" strokecolor="#2563eb" fillcolor="#2563eb">
                            <w:anchorlock/>
                            <center>
                        <![endif]-->
                        <a href="{{ $url }}" target="_blank" style="display:inline-block;padding:12px 32px;font-size:15px;font-weight:700;font-family:'Inter',Helvetica,Arial,sans-serif;color:#ffffff;text-decoration:none;border-radius:8px;letter-spacing:0.3px;line-height:24px;">
                            {{ $slot }}
                        </a>
                        <!--[if mso]>
                            </center>
                        </v:roundrect>
                        <![endif]-->
                    </td>
                </tr>
            </table>
        </td>
    </tr>
</table>
