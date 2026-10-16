<?php

namespace App\Http\Middleware;

use App\Models\Assignment;
use App\Models\AttendanceSession;
use App\Models\GradingComponent;
use App\Models\GradingItem;
use App\Models\Module;
use App\Models\ModuleFile;
use App\Models\Section;
use App\Models\Submission;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class EnsureFacultyResourceAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user?->isFaculty()) {
            return $next($request);
        }

        $routeName = (string) $request->route()?->getName();

        if (! Str::startsWith($routeName, [
            'sections.',
            'modules.',
            'module-files.',
            'assignments.',
            'submissions.',
            'components.',
            'items.',
            'attendance.',
            'class-record.',
        ])) {
            return $next($request);
        }

        if (! $request->route('sectionId')
            && ! $request->route('id')
            && ! $request->route('componentId')
            && ! $request->route('itemId')
            && ! $request->route('sessionId')) {
            return $next($request);
        }

        $section = $this->resolveSection($request, $routeName);

        abort_unless($section && (int) $section->faculty_id === (int) $user->id, 403);

        return $next($request);
    }

    private function resolveSection(Request $request, string $routeName): ?Section
    {
        $sectionId = $request->route('sectionId');

        if ($sectionId) {
            return $this->find(Section::class, $sectionId);
        }

        $id = $request->route('id')
            ?? $request->route('componentId')
            ?? $request->route('itemId')
            ?? $request->route('sessionId');

        if (! $id) {
            return null;
        }

        return match (true) {
            str_starts_with($routeName, 'sections.')
                => $this->find(Section::class, $id),
            str_starts_with($routeName, 'modules.')
                => $this->find(Module::class, $id)?->section,
            str_starts_with($routeName, 'module-files.')
                => $this->find(ModuleFile::class, $id)?->module?->section,
            str_starts_with($routeName, 'assignments.')
                => $this->find(Assignment::class, $id)?->section,
            str_starts_with($routeName, 'submissions.')
                => $this->find(Submission::class, $id)?->assignment?->section,
            str_starts_with($routeName, 'components.')
                => $this->find(GradingComponent::class, $id)?->section,
            str_starts_with($routeName, 'items.')
                => $this->find(GradingItem::class, $id)?->section,
            str_starts_with($routeName, 'attendance.')
                => $this->find(AttendanceSession::class, $id)?->section,
            default => null,
        };
    }

    private function find(string $model, string $encryptedId): mixed
    {
        try {
            return $model::query()->find(Crypt::decryptString($encryptedId));
        } catch (\Throwable) {
            abort(404);
        }
    }
}
