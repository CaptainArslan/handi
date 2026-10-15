{{-- One action per email. $outline = true for the quieter red "wasn't you" button. --}}
@php($outline = $outline ?? false)
<tr>
    <td style="padding:2px 0 18px;">
        <table role="presentation" cellpadding="0" cellspacing="0" border="0">
            <tr>
                <td class="btn-cell" style="border-radius:14px; background:{{ $outline ? '#FFFFFF' : '#0F5C4D' }}; {{ $outline ? 'border:1.5px solid #E7C9BF;' : '' }}">
                    <a href="{{ $url }}" target="_blank" style="display:inline-block; padding:14px 26px; font-size:15px; line-height:20px; font-weight:600; color:{{ $outline ? '#B3402A' : '#FFFFFF' }}; text-decoration:none; border-radius:14px;">{{ $label }}</a>
                </td>
            </tr>
        </table>
    </td>
</tr>
