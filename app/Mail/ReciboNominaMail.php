<?php

namespace App\Mail;

use App\Models\Nomina;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ReciboNominaMail extends Mailable
{
    use Queueable, SerializesModels;

    public Nomina $nomina;

    public function __construct(Nomina $nomina)
    {
        $this->nomina = $nomina;
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Tu nómina de {$this->nomina->mes_nombre} {$this->nomina->anio} - Manzer Agroforestal",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.recibo-nomina',
            with: [
                'nomina' => $this->nomina,
                'trabajador' => $this->nomina->trabajador,
            ],
        );
    }

    /**
     * Adjunta el recibo PDF generado al momento desde los datos de la nómina.
     *
     * @return array<int, \Illuminate\Mail\Mailables\Attachment>
     */
    public function attachments(): array
    {
        $pdf = Pdf::loadView('nominas.recibo', ['nomina' => $this->nomina]);
        $nombre = 'nomina_' . $this->nomina->mes_nombre . '_' . $this->nomina->anio . '.pdf';

        return [
            Attachment::fromData(fn () => $pdf->output(), $nombre)
                ->withMime('application/pdf'),
        ];
    }
}
