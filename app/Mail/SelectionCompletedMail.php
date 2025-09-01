<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class SelectionCompletedMail extends Mailable
{
    use Queueable, SerializesModels;

    public string $userName;
    public ?string $bannerUrl;
    public array $items;

    public function __construct(string $userName, array $items = [], ?string $bannerUrl = null)
    {
        $this->userName  = $userName;
        $this->items     = $items;
        $this->bannerUrl = $bannerUrl;
    }

    public function build()
    {
        return $this->subject('Tu selección ha sido registrada')
            ->view('emails.selection-completed')
            ->with([
                'currentYear' => now()->year,
                'userName'    => $this->userName,
                'bannerUrl'   => $this->bannerUrl ?: asset('assets/images/email-template/banner-default.jpg'),
                'items'       => $this->items, // por si la vista los usa
            ]);
    }
}
