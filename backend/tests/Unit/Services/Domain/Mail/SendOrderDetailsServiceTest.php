<?php

namespace Tests\Unit\Services\Domain\Mail;

use HiEvents\DomainObjects\AttendeeDomainObject;
use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\DomainObjects\EventSettingDomainObject;
use HiEvents\DomainObjects\OrderDomainObject;
use HiEvents\DomainObjects\OrganizerDomainObject;
use HiEvents\DomainObjects\Status\OrderPaymentStatus;
use HiEvents\DomainObjects\Status\OrderStatus;
use HiEvents\Mail\Order\OrderFailed;
use HiEvents\Mail\Order\OrderSummary;
use HiEvents\Mail\Organizer\OrderSummaryForOrganizer;
use HiEvents\Repository\Interfaces\EventRepositoryInterface;
use HiEvents\Repository\Interfaces\OrderRepositoryInterface;
use HiEvents\Services\Domain\Email\MailBuilderService;
use HiEvents\Services\Domain\Mail\SendOrderDetailsService;
use Illuminate\Mail\Mailer;
use Mockery;
use Mockery\MockInterface;
use Tests\TestCase;

class SendOrderDetailsServiceTest extends TestCase
{
    private const int ORDER_ID = 100;

    private const int EVENT_ID = 200;

    private OrderRepositoryInterface|MockInterface $orderRepository;

    private EventRepositoryInterface|MockInterface $eventRepository;

    private Mailer|MockInterface $mailer;

    private MailBuilderService|MockInterface $mailBuilderService;

    private SendOrderDetailsService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->orderRepository = Mockery::mock(OrderRepositoryInterface::class);
        $this->eventRepository = Mockery::mock(EventRepositoryInterface::class);
        $this->mailer = Mockery::mock(Mailer::class);
        $this->mailBuilderService = Mockery::mock(MailBuilderService::class);

        $this->orderRepository->shouldReceive('loadRelation')->andReturnSelf();
        $this->eventRepository->shouldReceive('loadRelation')->andReturnSelf();

        $this->service = new SendOrderDetailsService(
            $this->eventRepository,
            $this->orderRepository,
            $this->mailer,
            $this->mailBuilderService,
        );
    }

    public function test_send_order_summary_and_ticket_emails_sends_only_order_summary(): void
    {
        $organizer = (new OrganizerDomainObject)
            ->setEmail('organizer@example.com')
            ->setName('Organizer Name');

        $eventSettings = (new EventSettingDomainObject)
            ->setNotifyOrganizerOfNewOrders(true);

        $event = (new EventDomainObject)
            ->setId(self::EVENT_ID)
            ->setTitle('Test Event')
            ->setOrganizer($organizer)
            ->setEventSettings($eventSettings);

        $attendee1 = (new AttendeeDomainObject)
            ->setId(1)
            ->setEmail('attendee1@example.com');
        $attendee2 = (new AttendeeDomainObject)
            ->setId(2)
            ->setEmail('attendee2@example.com');

        $order = (new OrderDomainObject)
            ->setId(self::ORDER_ID)
            ->setEventId(self::EVENT_ID)
            ->setStatus(OrderStatus::COMPLETED->name)
            ->setEmail('buyer@example.com')
            ->setLocale('en')
            ->setIsManuallyCreated(false)
            ->setAttendees(collect([$attendee1, $attendee2]));

        $this->orderRepository->shouldReceive('findById')
            ->once()
            ->with(self::ORDER_ID)
            ->andReturn($order);

        $this->eventRepository->shouldReceive('findById')
            ->once()
            ->with(self::EVENT_ID)
            ->andReturn($event);

        $mockOrderSummaryMail = Mockery::mock(OrderSummary::class);

        $this->mailBuilderService->shouldReceive('buildOrderSummaryMail')
            ->once()
            ->with($order, $event, $eventSettings, $organizer, null, null)
            ->andReturn($mockOrderSummaryMail);

        $buyerMailerPending = Mockery::mock();
        $buyerMailerPending->shouldReceive('locale')
            ->once()
            ->with('en')
            ->andReturnSelf();
        $buyerMailerPending->shouldReceive('send')
            ->once()
            ->with($mockOrderSummaryMail);

        $organizerMailerPending = Mockery::mock();
        $organizerMailerPending->shouldReceive('send')
            ->once()
            ->with(Mockery::type(OrderSummaryForOrganizer::class));

        $this->mailer->shouldReceive('to')
            ->once()
            ->with('buyer@example.com')
            ->andReturn($buyerMailerPending);

        $this->mailer->shouldReceive('to')
            ->once()
            ->with('organizer@example.com')
            ->andReturn($organizerMailerPending);

        $this->service->sendOrderSummaryAndTicketEmails($order);
    }

    public function test_send_order_summary_and_ticket_emails_sends_order_failed_email(): void
    {
        $organizer = (new OrganizerDomainObject)
            ->setEmail('organizer@example.com');

        $eventSettings = new EventSettingDomainObject;

        $event = (new EventDomainObject)
            ->setId(self::EVENT_ID)
            ->setOrganizer($organizer)
            ->setEventSettings($eventSettings);

        $order = (new OrderDomainObject)
            ->setId(self::ORDER_ID)
            ->setEventId(self::EVENT_ID)
            ->setStatus(OrderStatus::CANCELLED->name)
            ->setPaymentStatus(OrderPaymentStatus::PAYMENT_FAILED->name)
            ->setEmail('buyer@example.com')
            ->setLocale('en')
            ->setIsManuallyCreated(false);

        $this->orderRepository->shouldReceive('findById')
            ->once()
            ->with(self::ORDER_ID)
            ->andReturn($order);

        $this->eventRepository->shouldReceive('findById')
            ->once()
            ->with(self::EVENT_ID)
            ->andReturn($event);

        $buyerMailerPending = Mockery::mock();
        $buyerMailerPending->shouldReceive('locale')
            ->once()
            ->with('en')
            ->andReturnSelf();
        $buyerMailerPending->shouldReceive('send')
            ->once()
            ->with(Mockery::type(OrderFailed::class));

        $this->mailer->shouldReceive('to')
            ->once()
            ->with('buyer@example.com')
            ->andReturn($buyerMailerPending);

        $this->service->sendOrderSummaryAndTicketEmails($order);
    }
}
