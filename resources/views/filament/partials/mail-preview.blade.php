{{-- D123: the e-mail in a sandboxed frame (no scripts, no links followed), exactly as rendered for sending. --}}
<iframe sandbox srcdoc="{{ $html }}" style="width:100%;height:{{ $height ?? '70vh' }};border:1px solid rgba(127,127,127,.3);border-radius:.6rem;background:#fff" title="{{ __('ই-মেলের নমুনা') }}"></iframe>
