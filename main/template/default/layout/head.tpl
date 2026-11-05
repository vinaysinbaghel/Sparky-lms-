<meta charset="{{ system_charset }}" />
<!-- Force latest IE rendering engine or ChromeFrame if installed -->
<!--[if IE]>
<meta http-equiv="X-UA-Compatible" content="IE=edge,chrome=1">
<![endif]-->
{{ prefetch }}
{{ favico }}
<link rel="icon" type="image/svg+xml" href="{{ _p.web }}favicon.svg?v=sparky" />
<link rel="icon" type="image/png" href="{{ _p.web }}web/css/themes/chamilo/images/favicon.png?v=sparky" />
<link rel="shortcut icon" href="{{ _p.web }}favicon.ico?v=sparky" type="image/x-icon" />
<link rel="apple-touch-icon" href="{{ _p.web }}apple-touch-icon.png?v=sparky" />
<meta name="apple-mobile-web-app-capable" content="yes" />
<meta name="application-name" content="Sparky LMS" />
<meta name="theme-color" content="#4F46E5" />
<meta name="generator" content="Sparky" />
{#  Use the latest engine in ie8/ie9 or use google chrome engine if available  #}
{#  Improve usability in portal devices #}
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>{{ title_string }}</title>
{{ social_meta }}
{{ css_static_file_to_string }}
{{ js_file_to_string }}
{{ extra_headers }}

{% if _s.language_interface %}
<script src="{{ _p.web }}web/build/main.{{ _s.language_interface }}.js"></script>
{% else %}{# language_interface *should* always be defined, so we should never come here #}
<script src="{{ _p.web }}web/build/main.js"></script>
{% endif %}

{{ css_custom_file_to_string }}
{{ css_style_print }}
