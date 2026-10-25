<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Actions\AddNoteAction;
use App\Actions\BulkProspectAction;
use App\Actions\ChangeStatusAction;
use App\Actions\ContactProspectAction;
use App\Actions\OptOutAction;
use App\Enums\PipelineStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\AiMessageRequest;
use App\Http\Requests\BulkRequest;
use App\Http\Requests\ChangeStatusRequest;
use App\Http\Requests\ContactRequest;
use App\Http\Requests\NoteRequest;
use App\Http\Resources\ActivityResource;
use App\Http\Resources\ProspectDetailResource;
use App\Models\Prospect;
use App\Repositories\ProspectRepository;
use App\Services\Ai\AiMessageService;
use App\Services\Contact\ContactPolicy;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProspectController extends Controller
{
    public function __construct(private readonly ProspectRepository $prospects) {}

    public function show(Prospect $prospect): JsonResponse
    {
        return response()->json($this->detail($prospect));
    }

    public function contactCheck(Request $request, Prospect $prospect, ContactPolicy $policy): JsonResponse
    {
        return response()->json($policy->check($prospect, $request->user())->toArray());
    }

    public function contact(ContactRequest $request, Prospect $prospect, ContactProspectAction $action): JsonResponse
    {
        $data = $request->validated();
        $prospect = $action->handle($prospect, $request->user(), $data['message'], $data['override_reason'] ?? null);

        return response()->json(['prospect' => $this->detail($prospect)]);
    }

    public function status(ChangeStatusRequest $request, Prospect $prospect, ChangeStatusAction $action): JsonResponse
    {
        $data = $request->validated();
        $prospect = $action->handle($prospect, PipelineStatus::from($data['status']), $request->user(), $data['rejection_reason_id'] ?? null);

        return response()->json(['prospect' => $this->detail($prospect)]);
    }

    public function note(NoteRequest $request, Prospect $prospect, AddNoteAction $action): JsonResponse
    {
        $activity = $action->handle($prospect, $request->user(), $request->validated()['body']);

        return response()->json(['activity' => ActivityResource::make($activity)->resolve()], 201);
    }

    public function optOut(Request $request, Prospect $prospect, OptOutAction $action): JsonResponse
    {
        $prospect = $action->optOut($prospect, $request->user());

        return response()->json(['prospect' => $this->detail($prospect)]);
    }

    public function optIn(Request $request, Prospect $prospect, OptOutAction $action): JsonResponse
    {
        $prospect = $action->optIn($prospect, $request->user());

        return response()->json(['prospect' => $this->detail($prospect)]);
    }

    public function messageAi(AiMessageRequest $request, Prospect $prospect, AiMessageService $ai): JsonResponse
    {
        abort_unless($ai->enabled(), 404, __('api.ai_disabled'));

        return response()->json([
            'message' => $ai->generate($prospect, $request->user()->name, $request->validated()['tone'] ?? 'ramah'),
        ]);
    }

    public function bulk(BulkRequest $request, BulkProspectAction $action): JsonResponse
    {
        $data = $request->validated();

        return response()->json($action->handle(
            $data['ids'],
            $data['action'],
            $request->user(),
            isset($data['status']) ? PipelineStatus::from($data['status']) : null,
            $data['rejection_reason_id'] ?? null,
        ));
    }

    /**
     * The Actions already return the refreshed model, so no second refresh here.
     *
     * @return array<string, mixed>
     */
    private function detail(Prospect $prospect): array
    {
        return ProspectDetailResource::make($this->prospects->detail($prospect))->resolve();
    }
}
