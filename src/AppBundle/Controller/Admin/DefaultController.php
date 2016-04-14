<?php

namespace AppBundle\Controller\Admin;

use AppBundle\Manager\ConnectionRequestManager;
use AppBundle\Manager\CityManager;
use JMS\DiExtraBundle\Annotation\Inject;
use JMS\DiExtraBundle\Annotation\InjectParams;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use AppBundle\Entity\City;

use AppBundle\DomainEvents;
use AppBundle\Entity\Connection;
use AppBundle\Entity\ConnectionRequest;
use AppBundle\Event\ConnectionCreatedEvent;

/**
 * @Route("admin")
 */
class DefaultController extends Controller
{
    /**
     * @var ConnectionRequestManager
     */
    private $connectionRequestManager;

    /**
     * @var CityManager
     */
    private $cityManager;

    /**
     * @InjectParams({
     *     "connectionRequestManager"   = @Inject("connection_request_manager"),
     *     "cityManager"                = @Inject("city_manager")
     * })
     */
    public function __construct(ConnectionRequestManager $connectionRequestManager, CityManager $cityManager)
    {
        $this->connectionRequestManager = $connectionRequestManager;
        $this->cityManager              = $cityManager;
    }

    /**
     * @Route("/{id}", name="admin_start", defaults={"id": null})
     * @Method("GET")
     */
    public function indexAction(Request $request, City $city = null)
    {
        $manager = $this->getDoctrine()->getManager();
        if ($request->isMethod('POST')) {
            /** @var ConnectionRequest $learnerConnectionRequest */
            $learnerConnectionRequest = $this->getConnectionRequestRepository()->find($request->request->getInt('learner'));
            /** @var ConnectionRequest $fluentSpeakerConnectionRequest */
            $fluentSpeakerConnectionRequest = $this->getConnectionRequestRepository()->find($request->request->getInt('fluentSpeaker'));
            if ($this->getConnectionRepository()->findForUsers(
                $learnerConnectionRequest->getUser(), $fluentSpeakerConnectionRequest->getUser()
            )) {
                $this->addFlash('error', sprintf(
                    'Personerna %s och %s har redan kopplats ihop tidigare',
                    $learnerConnectionRequest->getUser()->getName(),
                    $fluentSpeakerConnectionRequest->getUser()->getName()
                ));
            } else {
                $connection = new Connection($this->getUser());
                $connection->setLearner($learnerConnectionRequest->getUser());
                $connection->setFluentSpeaker($fluentSpeakerConnectionRequest->getUser());
                $connection->setCity($learnerConnectionRequest->getCity());
                $connection->setFluentSpeakerComment($fluentSpeakerConnectionRequest->getComment());
                $connection->setLearnerComment($learnerConnectionRequest->getComment());
                $connection->setMusicFriend($learnerConnectionRequest->isMusicFriend());
                $manager->persist($connection);
                $manager->remove($learnerConnectionRequest);
                $manager->remove($fluentSpeakerConnectionRequest);
                $manager->flush();
                $this->get('event_dispatcher')->dispatch(
                    DomainEvents::CONNECTION_CREATED,
                    new ConnectionCreatedEvent($connection)
                );
                $this->addFlash('info', sprintf(
                    'En koppling skapades melland %s och %s',
                    $learnerConnectionRequest->getUser()->getName(),
                    $fluentSpeakerConnectionRequest->getUser()->getName()
                ));
            }
            return $this->redirect($this->generateUrl('admin_start'));
        }
        if (!$city) {
            $city = $this->getCityRepository()->findAll()[0];
        }
        $type = $request->query->get('type');
        $learners = $this->getConnectionRequestRepository()->findForCity($city, true, $type == 'musicfriend');
        $fluentSpeakers = $this->getConnectionRequestRepository()->findForCity($city, false, $type == 'musicfriend');
        $cities = $this->getCityRepository()->findAll();
        if ($type == 'musicfriend') {
            $categories = $this->getMusicCategoryRepository()->findAll();
        } else {
            $categories = $this->getGeneralCategoryRepository()->findAll();
        }
        $parameters = [
            'categories' => $categories,
            'learners' => $learners,
            'fluentSpeakers' => $fluentSpeakers,
            'cities' => $cities,
            'city' => $city,
            'type' => $type,
        ];
        return $this->render('admin/default/index.html.twig', $parameters);
    }

    protected function getConnectionRequestRepository()
    {
        return $this->getDoctrine()->getManager()->getRepository('AppBundle:ConnectionRequest');
    }

    protected function getCityRepository()
    {
        return $this->getDoctrine()->getManager()->getRepository('AppBundle:City');
    }

    /**
     * @return \AppBundle\Entity\ConnectionRepository
     */
    protected function getConnectionRepository()
    {
        return $this->getDoctrine()->getManager()->getRepository('AppBundle:Connection');
    }

    protected function getCategoryRepository()
    {
        return $this->getDoctrine()->getManager()->getRepository('AppBundle:Category');
    }

    protected function getMusicCategoryRepository()
    {
        return $this->getDoctrine()->getManager()->getRepository('AppBundle:MusicCategory');
    }

    protected function getGeneralCategoryRepository()
    {
        return $this->getDoctrine()->getManager()->getRepository('AppBundle:GeneralCategory');
    }
}
