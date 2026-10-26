@php
    // Kasia rarely opens the list on her own, so the dashboard says when something waits on it.
    $failed = app(\App\Modules\Monitoring\Support\FailedMails::class)->all()->count();
    $message = match (true) {
        $failed === 1 => '1 mail nie wyszedł',
        in_array($failed % 10, [2, 3, 4], true) && ! in_array($failed % 100, [12, 13, 14], true) => $failed.' maile nie wyszły',
        default => $failed.' maili nie wyszło',
    };
@endphp
@if ($failed > 0)
    <a href="{{ route('admin.failed-mails.index') }}" class="mb-5 flex flex-wrap items-center justify-between gap-x-5 gap-y-2 rounded-[4px] border border-alert-line bg-alert px-[22px] py-4 text-alert-text hover:border-error hover:text-alert-text">
        <span class="text-[14.5px]">{{ $message }} mimo kilku prób.</span>
        <span class="text-[13px] underline underline-offset-4">Zobacz, które →</span>
    </a>
@endif
