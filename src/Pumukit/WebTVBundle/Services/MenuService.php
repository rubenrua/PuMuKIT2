<?php

namespace Pumukit\WebTVBundle\Services;

use Doctrine\ODM\MongoDB\DocumentManager;
use Pumukit\SchemaBundle\Services\EmbeddedEventSessionService;
use Pumukit\LiveBundle\Document\Live;
use Pumukit\LiveBundle\Document\Event;

/**
 * Class MenuService.
 */
class MenuService
{
    /**
     * @var DocumentManager
     */
    private $dm;
    /**
     * @var EmbeddedEventSessionService
     */
    private $eventSessionService;
    private $advanceEvents;

    /**
     * MenuService constructor.
     *
     * @param DocumentManager             $documentManager
     * @param EmbeddedEventSessionService $eventSessionService
     * @param                             $advanceEvents
     */
    public function __construct(DocumentManager $documentManager, EmbeddedEventSessionService $eventSessionService, $advanceEvents)
    {
        $this->dm = $documentManager;
        $this->eventSessionService = $eventSessionService;
        $this->advanceEvents = $advanceEvents;
    }

    /**
     * @return array
     *
     * @throws \Exception
     */
    public function getMenuEventsElement()
    {
        if (!$this->advanceEvents) {
            list($events, $channels, $liveEventTypeSession) = $this->getEvents();
        } else {
            list($events, $channels, $liveEventTypeSession) = $this->getAdvanceEvents();
        }

        return [$events, $channels, $liveEventTypeSession];
    }

    /**
     * @return array
     *
     * @throws \Exception
     */
    private function getAdvanceEvents()
    {
        $events = $this->eventSessionService->findEventsMenu();
        $menuEvents = [];
        $nowOrFuture = false;
        foreach ($events as $event) {
            foreach ($event['data'] as $sessionData) {
                $sec = $sessionData['session']['ends']->sec;
                $date = new \DateTime();
                $ends = $date->setTimestamp($sec);
                if (new \DateTime() < $ends) {
                    $nowOrFuture = true;
                }
                $sessionStart = $sessionData['session']['start']->sec;
                $todayEnds = strtotime(date('Y-m-d H:i:s', mktime(23, 59, 59, date('m'), date('d'), date('Y'))));
                if ($sessionStart > $todayEnds) {
                    $nowOrFuture = false;
                }
                if ($nowOrFuture) {
                    $menuEvents[(string) $event['_id']] = [];
                    $menuEvents[(string) $event['_id']]['sort'] = $sessionStart;
                    $menuEvents[(string) $event['_id']]['event'] = $sessionData['event'];
                    $menuEvents[(string) $event['_id']]['sessions'][] = $sessionData['session'];
                    $nowOrFuture = false;
                }
            }
        }
        uasort(
            $menuEvents,
            function ($a, $b) {
                if ($a['sort'] == $b['sort']) {
                    return 0;
                }

                return ($a < $b) ? -1 : 1;
            }
        );

        return [
            $menuEvents,
            [],
            true,
        ];
    }

    /**
     * @return array
     */
    private function getEvents()
    {
        $channels = $this->dm->getRepository(Live::class)->findAll();
        $events = $this->dm->getRepository(Event::class)->findNextEvents();
        $liveEventTypeSession = false;

        return [
            $channels,
            $events,
            $liveEventTypeSession,
        ];
    }
}
