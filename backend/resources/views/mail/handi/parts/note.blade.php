{{-- Soft note box. $tone: cream (default) or mint. $html is trusted template markup. --}}
@php($mint = ($tone ?? 'cream') === 'mint')
<tr>
    <td style="padding:2px 0 18px;">
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
            <tr>
                <td style="background:{{ $mint ? '#E3F0EB' : '#FBF7F1' }}; border-radius:14px; padding:12px 14px; font-size:13px; line-height:20px; color:{{ $mint ? '#234B41' : '#4A453D' }};">{!! $html !!}</td>
            </tr>
        </table>
    </td>
</tr>
