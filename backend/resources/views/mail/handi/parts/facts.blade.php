{{-- Label / value rows ($rows = ['When' => '…', …]). --}}
<tr>
    <td style="padding:2px 0 18px;">
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="border:1px solid #EEE7DC; border-radius:16px; border-collapse:separate;">
            @foreach ($rows as $label => $value)
                <tr>
                    <td style="padding:11px 14px; font-size:13.5px; line-height:20px; color:#4A453D; {{ $loop->first ? '' : 'border-top:1px solid #EEE7DC;' }}">{{ $label }}</td>
                    <td align="right" style="padding:11px 14px; font-size:13.5px; line-height:20px; font-weight:600; color:#1E1B16; word-break:break-word; {{ $loop->first ? '' : 'border-top:1px solid #EEE7DC;' }}">{{ $value }}</td>
                </tr>
            @endforeach
        </table>
    </td>
</tr>
