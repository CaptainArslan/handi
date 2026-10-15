{{--
    Handi email layout — same look as the app (Handi green, amber, sand, Poppins).
    Table-based with inline styles so it renders the same in Gmail, Outlook and Apple Mail.
    Sections: preheader (inbox preview line), eyebrow, eyebrowTone (green|warm|alert), heading, content, why (footer reason).
--}}
@php
    $font = "'Poppins', 'Segoe UI', Helvetica, Arial, sans-serif";
    $tones = [
        'green' => ['#E3F0EB', '#0F5C4D'],
        'warm' => ['#FDF0D9', '#8A5A0B'],
        'alert' => ['#F8E1DA', '#B3402A'],
    ];
    [$eyebrowBg, $eyebrowFg] = $tones[trim($__env->yieldContent('eyebrowTone', 'green'))] ?? $tones['green'];
    $support = config('handi.support_email');
@endphp
<!DOCTYPE html>
<html lang="en" xmlns="http://www.w3.org/1999/xhtml">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="x-apple-disable-message-reformatting">
    <meta name="color-scheme" content="light only">
    <meta name="supported-color-schemes" content="light only">
    <title>@yield('heading')</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        body { margin: 0; padding: 0; background: #F1EBE1; }
        a { color: #0F5C4D; }
        @media only screen and (max-width: 620px) {
            .card { padding: 26px 20px 22px !important; }
            .h1 { font-size: 21px !important; line-height: 28px !important; }
            .digits { font-size: 30px !important; letter-spacing: 7px !important; }
            .btn-cell, .btn-cell a { display: block !important; width: 100% !important; text-align: center !important; box-sizing: border-box; }
            .step { display: block !important; width: 100% !important; padding: 0 0 8px 0 !important; }
        }
    </style>
</head>
<body style="margin:0; padding:0; background:#F1EBE1; -webkit-text-size-adjust:100%;">
    {{-- Inbox preview text (hidden) --}}
    <div style="display:none; max-height:0; overflow:hidden; mso-hide:all; font-size:1px; line-height:1px; color:#F1EBE1;">@yield('preheader')&#847;&zwnj;&nbsp;&#847;&zwnj;&nbsp;&#847;&zwnj;&nbsp;&#847;&zwnj;&nbsp;</div>

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background:#F1EBE1;">
        <tr>
            <td align="center" style="padding:28px 12px 36px;">
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="max-width:600px;">
                    {{-- Brand --}}
                    <tr>
                        <td style="padding:0 8px 18px;">
                            <table role="presentation" cellpadding="0" cellspacing="0" border="0">
                                <tr>
                                    <td style="padding-right:10px; vertical-align:middle;">
                                        <img src="{{ asset('images/brand/handi-email-logo.png') }}" width="34" height="50" alt="" style="display:block; border:0; width:34px; height:auto;">
                                    </td>
                                    <td style="vertical-align:middle; font-family:{!! $font !!};">
                                        <div style="font-size:20px; line-height:22px; font-weight:700; color:#6B2F1A;">Handi</div>
                                        <div style="font-size:11px; line-height:16px; font-style:italic; color:#6E675C;">Aaj kya pakaen?</div>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    {{-- Card --}}
                    <tr>
                        <td class="card" style="background:#FFFFFF; border-radius:22px; padding:34px 32px 30px; font-family:{!! $font !!}; color:#1E1B16;">
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
                                <tr>
                                    <td style="padding-bottom:14px;">
                                        <span style="display:inline-block; background:{{ $eyebrowBg }}; color:{{ $eyebrowFg }}; font-size:11px; line-height:14px; font-weight:600; letter-spacing:1px; text-transform:uppercase; padding:5px 10px; border-radius:999px;">@yield('eyebrow')</span>
                                    </td>
                                </tr>
                                <tr>
                                    <td class="h1" style="padding-bottom:12px; font-size:24px; line-height:31px; font-weight:700; color:#1E1B16;">@yield('heading')</td>
                                </tr>
                                @yield('content')
                            </table>
                        </td>
                    </tr>

                    {{-- Footer --}}
                    <tr>
                        <td style="padding:20px 8px 0; font-family:{!! $font !!}; font-size:12px; line-height:19px; color:#857D70;">
                            <p style="margin:0 0 6px;">@yield('why')</p>
                            <p style="margin:0 0 6px;">Questions? Reply to this email or write to <a href="mailto:{{ $support }}" style="color:#0F5C4D;">{{ $support }}</a>.</p>
                            <p style="margin:0;">Handi · {{ config('handi.app_name') }}</p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
