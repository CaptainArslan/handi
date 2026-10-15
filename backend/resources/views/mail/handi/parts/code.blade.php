{{-- 6-digit code in the amber box, expiry underneath. --}}
<tr>
    <td style="padding:2px 0 18px;">
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background:#FDF0D9; border:1.5px dashed #F2A93B; border-radius:18px;">
            <tr>
                <td align="center" style="padding:20px 12px 6px;">
                    <span class="digits" style="font-size:36px; line-height:44px; font-weight:700; letter-spacing:10px; color:#1E1B16; font-variant-numeric:tabular-nums;">{{ trim(chunk_split($code, 3, ' ')) }}</span>
                </td>
            </tr>
            <tr>
                <td align="center" style="padding:0 12px 18px; font-size:12.5px; line-height:18px; font-weight:600; color:#8A5A0B;">Expires in {{ $minutes }} minutes · works once</td>
            </tr>
        </table>
    </td>
</tr>
