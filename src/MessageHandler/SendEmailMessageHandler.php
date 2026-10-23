<?php

namespace App\MessageHandler;

use App\Message\SendEmailMessage;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class SendEmailMessageHandler
{
    public function __construct(
        private MailerInterface $mailer,
        #[Autowire('%env(MAILER_FROM)%')] private string $from,
    ) {}

    public function __invoke(SendEmailMessage $message): void
    {
        $this->mailer->send(
            (new TemplatedEmail())
                ->from($this->from)
                ->to($message->to)
                ->subject($message->subject)
                ->htmlTemplate($message->template)
                ->context($message->context)
        );
    }
}