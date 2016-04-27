<?php

namespace AppBundle\Manager;

use JMS\DiExtraBundle\Annotation\Inject;
use JMS\DiExtraBundle\Annotation\InjectParams;
use JMS\DiExtraBundle\Annotation\Service;
use AppBundle\Entity\ConnectionRequestRepository;
use AppBundle\Entity\ConnectionRequest;
use AppBundle\Entity\City;
use AppBundle\Entity\User;
use Pagerfanta\Pagerfanta;
use AppBundle\Manager\UserManager;
use Pagerfanta\Adapter\DoctrineORMAdapter;
use Symfony\Component\Translation\TranslatorInterface;

/**
 * @Service("connection_request_manager")
 */
class ConnectionRequestManager implements ManagerInterface
{
    /**
     * @var ConnectionRequestRepository
     */
    private $connectionRequestRepository;

    /**
     * @var UserManager
     */
    private $userManager;

    /**
     * @var TranslatorInterface
     */
    private $translator;

    /**
     * @InjectParams({
     *     "connectionRequestRepository" = @Inject("connection_request_repository"),
     *     "userManager" = @Inject("user_manager"),
     *     "translator" = @Inject("translator")
     * })
     */
    public function __construct(ConnectionRequestRepository $connectionRequestRepository, UserManager $userManager, TranslatorInterface $translator)
    {
        $this->connectionRequestRepository  = $connectionRequestRepository;
        $this->userManager                  = $userManager;
        $this->translator                   = $translator;
    }

    /**
     * @return ConnectionRequest
     */
    public function createNew()
    {
        return new ConnectionRequest();
    }

    /**
     * @param $entity
     * @return ConnectionRequest
     */
    public function save($entity)
    {
        return $this->connectionRequestRepository->save($entity);
    }

    /**
     * @param $id
     * @return null|object
     */
    public function getFind($id)
    {
        return $this->connectionRequestRepository->find($id);
    }

    /**
     * @param $entity
     */
    public function remove($entity)
    {
        $this->connectionRequestRepository->remove($entity);
    }

    /**
     * @param City $city
     * @return array
     */
    public function getFindNewWithinCity(City $city)
    {
        return $this->connectionRequestRepository->findNewWithinCity($city);
    }

    /**
     * @param City $city
     * @return array
     */
    public function getFindEstablishedWithinCity(City $city)
    {
        return $this->connectionRequestRepository->findEstablishedWithinCity($city);
    }

    /**
     * @param City $city
     * @return array
     */
    public function getFindEstablishedMusicFriendWithinCity(City $city)
    {
        return $this->connectionRequestRepository->findEstablishedMusicFriendWithinCity($city);
    }

    /**
     * @param User $user
     * @return null|object
     */
    public function getFindOneByUser(User $user)
    {
        return $this->connectionRequestRepository->findOneByUser($user);
    }

    /**
     * @param User $user
     * @return bool
     */
    public function userHasActiveRequest(User $user)
    {
        return $this->countUserActiveRequests($user)? true: false;
    }

    /**
     * @return array
     */
    public function getFindAll()
    {
        return $this->connectionRequestRepository->findAll();
    }

    /**
     * @param City $city
     * @return array
     */
    public function getFindCityStats(City $city)
    {
        return $this->connectionRequestRepository->findCityStats($city);
    }

    /**
     * @param City $city
     * @return array
     */
    public function getFindCity(City $city)
    {
        return $this->connectionRequestRepository->findCity($city);
    }

    /**
     * @param City $city
     * @param int $page
     * @return array
     */
    public function getFindPaginatedByCityResults(City $city, $page = 1)
    {
        $qb         = $this->connectionRequestRepository->findByCityQueryBuilder($city);
        $adapter    = new DoctrineORMAdapter($qb);
        $pagerfanta = new Pagerfanta($adapter);
        $pagerfanta->setMaxPerPage(100000);
        $pagerfanta->setCurrentPage($page);

        return [
            'success'                       => true,
            'newUsers'                      => count($this->getFindNewWithinCity($city)),
            'establishedUsers'              => count($this->getFindEstablishedWithinCity($city)),
            'establishedMusicFriendUsers'   => count($this->getFindEstablishedMusicFriendWithinCity($city)),
            'results'                       => $this->getCityResultsdByPagination($pagerfanta),
            'next'                          => ($pagerfanta->hasNextPage()? $pagerfanta->getNextPage(): false)
        ];
    }

    /**
     * @param Pagerfanta $pagerfanta
     * @return array
     */
    private function getCityResultsdByPagination(Pagerfanta $pagerfanta)
    {
        $datas              = [];
        $connectionRequests = $pagerfanta->getCurrentPageResults();

        foreach ($connectionRequests as $connectionRequest) {
            $pending    = $connectionRequest->getPending()? 1: 0;
            $datas[]    = [
                'request_date'  => $connectionRequest->getCreatedAt()->format('Y-m-d'),
                'name'          => $connectionRequest->getUser()->getFullName(),
                'email'         => $connectionRequest->getUser()->getEmail(),
                'category'      => $this->userManager->getWantToLearnTypeName($connectionRequest->getUser()),
                'action'        => $connectionRequest->getUser()->getId().'|'.$connectionRequest->getId().'|'.$pending //user_id|request_id|pending
            ];
        }

        return $datas;
    }

    /**
     * @param $userId
     * @return null|object
     */
    public function getFindOneUnpendingByUserId($userId)
    {
        return $this->connectionRequestRepository->findOneUnpendingByUserId($userId);
    }

    /**
     * @param $userId
     * @return null|object
     */
    public function getFindOneByUserId($userId)
    {
        return $this->connectionRequestRepository->findOneByUserId($userId);
    }

    /**
     * @param User $user
     * @return array
     */
    public function getFindAllPending(User $user)
    {
        return $this->connectionRequestRepository->findAllPending($user);
    }

    /**
     * @param User $user
     * @return array
     */
    public function getFindAllUninspected(User $user)
    {
        return $this->connectionRequestRepository->findAllByInspected($user, false);
    }

    /**
     * @param $id
     * @return bool
     */
    public function markAsInspected($id)
    {
        $connectionRequest = $this->getFind($id);

        if ($connectionRequest instanceof ConnectionRequest) {
            $connectionRequest->setInspected(true);

            $this->save($connectionRequest);

            return true;
        }

        return false;
    }

    /**
     * @param $id
     * @return bool
     */
    public function markAsPendingOrUnpending($id)
    {
        $connectionRequest = $this->getFind($id);

        if ($connectionRequest instanceof ConnectionRequest) {

            if ($connectionRequest->getPending()) {
                $connectionRequest->setPending(false);
            } else {
                $connectionRequest->setPending(true);
            }

            $this->save($connectionRequest);

            return $connectionRequest;
        }

        return false;
    }
}