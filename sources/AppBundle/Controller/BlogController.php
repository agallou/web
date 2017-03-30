<?php


namespace AppBundle\Controller;

use AppBundle\Event\Model\Planning;
use AppBundle\Event\Model\Repository\SpeakerRepository;
use AppBundle\Event\Model\Repository\TalkRepository;
use AppBundle\Event\Model\Room;
use AppBundle\Event\Model\Talk;
use Symfony\Component\HttpFoundation\Response;

class BlogController extends EventBaseController
{
    /**
     * @param $eventSlug
     * @return Response
     */
    public function programAction($eventSlug)
    {
        $event = $this->checkEventSlug($eventSlug);

        /**
         * @var $talkRepository TalkRepository
         */
        $talkRepository = $this->get('ting')->get(TalkRepository::class);
        $talks = $talkRepository->getByEventWithSpeakers($event);

        return $this->render(':blog:program.html.twig', ['talks' => $talks, 'event' => $event]);
    }
    /**
     * @param $eventSlug
     * @return Response
     */
    public function planningAction($eventSlug)
    {
        $event = $this->checkEventSlug($eventSlug);

        /**
         * @var $talkRepository TalkRepository
         */
        $talkRepository = $this->get('ting')->get(TalkRepository::class);
        $talks = $talkRepository->getByEventWithSpeakers($event);

        $eventPlanning = [];
        $rooms = [];

        foreach ($talks as $talkWithData) {
            /**
             * @var $talk Talk
             */
            $talk = $talkWithData['talk'];

            /**
             * @var $planning Planning
             */
            $planning = $talkWithData['planning'];

            /**
             * @var $room Room
             */
            $room = $talkWithData['room'];

            if ($planning === null) {
                continue;
            }

            $startDay = $planning->getStart()->format('d/m/Y');
            if (isset($eventPlanning[$startDay]) === false) {
                $eventPlanning[$startDay] = [];
            }

            $start = $planning->getStart()->setTimezone(new \DateTimeZone('Europe/Paris'))->format('d/m/Y H:i');

            if (isset($eventPlanning[$startDay][$start])=== false) {
                $eventPlanning[$startDay][$start] = [];
            }

            $interval = $planning->getEnd()->diff($planning->getStart());
            $talkWithData['length'] = $interval->i + $interval->h * 60;

            $eventPlanning[$startDay][$start][$room->getId()] = $talkWithData;

            if (isset($rooms[$room->getId()]) === false) {
                $rooms[$room->getId()] = $room;
            }
        }

        return $this->render(
            ':blog:planning.html.twig',
                [
                    'planning' => $eventPlanning,
                    'event' => $event,
                    'rooms' => $rooms,
                    'hourMin' => 8,
                    'hourMax' => 18,
                    'precision' => 5
                ]
        );
    }

    /**
     * @param $eventSlug
     * @return Response
     */
    public function speakersAction($eventSlug)
    {
        $event = $this->checkEventSlug($eventSlug);

        /**
         * @var $speakerRepository SpeakerRepository
         */
        $speakerRepository = $this->get('ting')->get(SpeakerRepository::class);
        $speakers = $speakerRepository->getScheduledSpeakersByEvent($event);

        return $this->render(':blog:speakers.html.twig', ['speakers' => $speakers, 'event' => $event]);
    }
}