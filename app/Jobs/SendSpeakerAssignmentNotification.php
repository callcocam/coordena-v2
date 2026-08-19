<?php

namespace App\Jobs;

use App\Enums\SpeakerNotificationKind;
use App\Enums\SpeakerNotificationStatus;
use App\Enums\TalkAssignmentStatus;
use App\Models\TalkAssignment;
use App\Models\TalkAssignmentNotification;
use App\Models\User;
use App\Services\PublicTalks\TalkAssignmentMessage;
use App\Support\Phone;
use Callcocam\WhatsAppCloud\Exceptions\CloudApiException;
use Callcocam\WhatsAppCloud\Facades\WhatsApp;
use Callcocam\WhatsAppCloud\Messages\TemplateMessage;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

/**
 * Envia ao orador o template de designação (ou lembrete) via WhatsApp Cloud.
 *
 * O registro `Pending` é criado na despachada ({@see queue()}), então cada
 * reenvio é uma nova linha e as tentativas da fila reutilizam a mesma. Sucesso
 * grava `wamid`/`sent_at` e move o assignment `scheduled → notified`; erro
 * terminal da Meta (ou esgotar as tentativas) marca a notificação `failed`.
 */
class SendSpeakerAssignmentNotification implements ShouldQueue
{
    use Queueable;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 3;

    /**
     * The number of seconds to wait before retrying the job.
     */
    public int $backoff = 60;

    public function __construct(
        public TalkAssignmentNotification $notification,
    ) {}

    /**
     * Create the Pending notification row and dispatch the send.
     */
    public static function queueFor(
        TalkAssignment $assignment,
        SpeakerNotificationKind $kind,
        ?User $sentBy = null,
        ?int $delaySeconds = null,
    ): TalkAssignmentNotification {
        /** @var TalkAssignmentNotification $notification */
        $notification = $assignment->notifications()->create([
            'speaker_id' => $assignment->speaker_id,
            'kind' => $kind,
            'status' => SpeakerNotificationStatus::Pending,
            'sent_by_id' => $sentBy?->id,
        ]);

        self::dispatch($notification)->delay($delaySeconds);

        return $notification;
    }

    /**
     * Create the Pending notification row and send it synchronously, in the
     * current request. Used by the manual buttons on the schedule screen so
     * the message goes out immediately, without depending on the queue
     * worker. A send failure bubbles up to the caller (the row is marked
     * `failed` by {@see failed()}).
     */
    public static function sendNowFor(
        TalkAssignment $assignment,
        SpeakerNotificationKind $kind,
        ?User $sentBy = null,
    ): TalkAssignmentNotification {
        /** @var TalkAssignmentNotification $notification */
        $notification = $assignment->notifications()->create([
            'speaker_id' => $assignment->speaker_id,
            'kind' => $kind,
            'status' => SpeakerNotificationStatus::Pending,
            'sent_by_id' => $sentBy?->id,
        ]);

        self::dispatchSync($notification);

        return $notification;
    }

    /**
     * Execute the job.
     */
    public function handle(TalkAssignmentMessage $message): void
    {
        if ($this->notification->status !== SpeakerNotificationStatus::Pending) {
            return;
        }

        $assignment = $this->notification->assignment;
        $phone = Phone::normalize($this->notification->speaker?->phone);

        if ($phone === null) {
            $this->fail(new CloudApiException('Speaker has no valid WhatsApp phone number.'));

            return;
        }

        $kind = $this->notification->kind;

        try {
            $result = WhatsApp::for($assignment->team)->sendTemplate(
                $phone,
                TemplateMessage::make($message->templateKey($assignment, $kind), $message->params($assignment, $kind)),
            );
        } catch (CloudApiException $exception) {
            if ($exception->isTerminal()) {
                $this->fail($exception);

                return;
            }

            throw $exception;
        }

        $this->notification->update([
            'wamid' => $result->messageId,
            'status' => SpeakerNotificationStatus::Sent,
            'sent_at' => now(),
        ]);

        if ($assignment->status === TalkAssignmentStatus::Scheduled) {
            $assignment->update(['status' => TalkAssignmentStatus::Notified]);
        }
    }

    /**
     * Mark the notification as failed once the job gives up.
     */
    public function failed(?Throwable $exception): void
    {
        $this->notification->fresh()?->update([
            'status' => SpeakerNotificationStatus::Failed,
            'response_payload' => ['error' => $exception?->getMessage()],
        ]);
    }
}
