@php
    $subgroup = $lesson->participants->firstWhere('student_group_id', $group->id)?->subgroup;
    $isStream = $lesson->participants->pluck('student_group_id')->unique()->count() > 1;
    $remote = $lesson->format === \App\Enums\LessonFormat::Remote;
@endphp
<button type="button"
    @class(['sg-lesson', 'sg-fixed' => $lesson->is_fixed_block, 'sg-override' => filled($lesson->override_reason)])
    title="{{ $lesson->discipline?->name ?? $lesson->title }}{{ $lesson->override_reason ? "\nПопри конфлікт: {$lesson->override_reason}" : '' }}"
    wire:click="mountAction('editLesson', @js(['lesson' => $lesson->id]))">
    <div class="sg-title">
        @if ($lesson->is_pinned)⚑ @endif{{ $lesson->displayTitle() }}
    </div>
    <div class="sg-meta">
        @if ($subgroup)<span class="sg-tag">{{ $subgroup->name }}</span>@endif
        @if ($isStream)<span class="sg-tag">потік</span>@endif
        @if ($remote)<span class="sg-tag">дист.</span>@endif
        {{ $lesson->teachers->pluck('short_name')->implode(', ') }}
    </div>
    @unless ($remote)
        <div class="sg-meta">{{ $lesson->rooms->map->label()->implode(', ') }}</div>
    @endunless
</button>
