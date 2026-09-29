<?php

namespace App\Mail\Report;

use App\Enum\MailPurpose;
use App\Mail\Concerns\HasMailPurpose;
use App\Models\Report\GeneratedReport;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Attachment;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Delivers a scheduled report to its recipients, using
 * {@see MailPurpose::Notifications}. The file is attached straight from the
 * default disk when small enough; otherwise the email links to the Reports
 * page, which still requires signing in to download.
 */
class ReportReadyMail extends Mailable
{
    use HasMailPurpose, Queueable, SerializesModels;

    protected MailPurpose $purpose = MailPurpose::Notifications;

    public function __construct(public GeneratedReport $report, public bool $attachFile = true) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('dashboard.reports.mail.subject', ['title' => $this->report->title, 'period' => $this->report->range()->label()]),
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.report.ready',
            with: [
                'report' => $this->report,
                'period' => $this->report->range()->label(),
                'attached' => $this->attachFile,
                'url' => route('admin.dashboard.reports'),
            ],
        );
    }

    /** @return list<Attachment> */
    public function attachments(): array
    {
        if (! $this->attachFile || ! $this->report->isDownloadable()) {
            return [];
        }

        return [
            Attachment::fromStorage($this->report->file_path)
                ->as($this->report->downloadName())
                ->withMime($this->report->format->mimeType()),
        ];
    }
}
