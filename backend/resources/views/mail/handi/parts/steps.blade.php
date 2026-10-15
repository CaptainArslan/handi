{{-- Pick → Cook → Log, the same three steps as the Today screen. --}}
@php($steps = [['Pick', 'Choose one of today’s three dishes.'], ['Cook', 'Make it for lunch or dinner.'], ['Log', 'Save it, so it won’t repeat too soon.']])
<tr>
    <td style="padding:2px 0 18px;">
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
            <tr>
                @foreach ($steps as $i => [$title, $text])
                    <td class="step" width="33%" valign="top" style="padding:0 {{ $loop->last ? '0' : '8px' }} 0 0;">
                        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
                            <tr>
                                <td style="background:#FBF7F1; border-radius:16px; padding:14px;">
                                    <div style="width:26px; height:26px; line-height:26px; border-radius:13px; background:#0F5C4D; color:#FFFFFF; font-size:12px; font-weight:700; text-align:center;">{{ $i + 1 }}</div>
                                    <div style="padding-top:8px; font-size:14px; line-height:20px; font-weight:600; color:#1E1B16;">{{ $title }}</div>
                                    <div style="font-size:12.5px; line-height:18px; color:#4A453D;">{{ $text }}</div>
                                </td>
                            </tr>
                        </table>
                    </td>
                @endforeach
            </tr>
        </table>
    </td>
</tr>
