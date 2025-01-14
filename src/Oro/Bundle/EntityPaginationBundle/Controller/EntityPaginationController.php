<?php

namespace Oro\Bundle\EntityPaginationBundle\Controller;

use Oro\Bundle\EntityBundle\ORM\DoctrineHelper;
use Oro\Bundle\EntityBundle\Tools\EntityRoutingHelper;
use Oro\Bundle\EntityPaginationBundle\Manager\MessageManager;
use Oro\Bundle\EntityPaginationBundle\Navigation\EntityPaginationNavigation;
use Oro\Bundle\EntityPaginationBundle\Navigation\NavigationResult;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;

/**
 * Handles navigation to previous/next entities in the entity paginator.
 */
class EntityPaginationController extends AbstractController
{
    /**
     * @Route("/first/{_entityName}/{_scope}/{_routeName}", name="oro_entity_pagination_first")
     *
     * @param $_entityName
     * @param $_scope
     * @param $_routeName
     * @return JsonResponse
     */
    public function firstAction($_entityName, $_scope, $_routeName)
    {
        return $this->getLink($_entityName, $_scope, $_routeName, EntityPaginationNavigation::FIRST);
    }

    /**
     * @Route("/previous/{_entityName}/{_scope}/{_routeName}", name="oro_entity_pagination_previous")
     *
     * @param $_entityName
     * @param $_scope
     * @param $_routeName
     * @return JsonResponse
     */
    public function previousAction($_entityName, $_scope, $_routeName)
    {
        return $this->getLink($_entityName, $_scope, $_routeName, EntityPaginationNavigation::PREVIOUS);
    }

    /**
     * @Route("/next/{_entityName}/{_scope}/{_routeName}", name="oro_entity_pagination_next")
     *
     * @param $_entityName
     * @param $_scope
     * @param $_routeName
     * @return JsonResponse
     */
    public function nextAction($_entityName, $_scope, $_routeName)
    {
        return $this->getLink($_entityName, $_scope, $_routeName, EntityPaginationNavigation::NEXT);
    }

    /**
     * @Route("/last/{_entityName}/{_scope}/{_routeName}", name="oro_entity_pagination_last")
     *
     * @param $_entityName
     * @param $_scope
     * @param $_routeName
     * @return JsonResponse
     */
    public function lastAction($_entityName, $_scope, $_routeName)
    {
        return $this->getLink($_entityName, $_scope, $_routeName, EntityPaginationNavigation::LAST);
    }

    /**
     * @param string $entityName
     * @param string $scope
     * @param string $routeName
     * @param string $navigation
     * @return JsonResponse
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     */
    protected function getLink($entityName, $scope, $routeName, $navigation): JsonResponse
    {
        $doctrineHelper = $this->get(DoctrineHelper::class);
        $navigationService = $this->get(EntityPaginationNavigation::class);

        $params = $this->get('request_stack')->getCurrentRequest()->query->all();

        $entityName = $this->get(EntityRoutingHelper::class)->resolveEntityClass($entityName);
        $identifier = $doctrineHelper->getSingleEntityIdentifierFieldName($entityName);
        $message = null;

        if (!empty($params[$identifier])) {
            $identifierValue = $params[$identifier];
            $entity = $doctrineHelper->getEntityReference($entityName, $identifierValue);

            switch ($navigation) {
                case EntityPaginationNavigation::FIRST:
                    $result = $navigationService->getFirstIdentifier($entity, $scope);
                    break;
                case EntityPaginationNavigation::PREVIOUS:
                    $result = $navigationService->getPreviousIdentifier($entity, $scope);
                    break;
                case EntityPaginationNavigation::NEXT:
                    $result = $navigationService->getNextIdentifier($entity, $scope);
                    break;
                case EntityPaginationNavigation::LAST:
                    $result = $navigationService->getLastIdentifier($entity, $scope);
                    break;
            }

            /** @var NavigationResult $result */
            if ($result instanceof NavigationResult) {
                $entityId = $result->getId();
                if ($entityId) {
                    $params[$identifier] = $entityId;
                }

                $messageManager = $this->get(MessageManager::class);

                if (!$result->isAvailable()) {
                    $message = $messageManager->getNotAvailableMessage($entity, $scope);
                } elseif (!$result->isAccessible()) {
                    $message = $messageManager->getNotAccessibleMessage($entity, $scope);
                }
            }
        }

        $url = $this->generateUrl($routeName, $params);

        return new JsonResponse(['url' => $url, 'message' => $message]);
    }

    /**
     * {@inheritdoc}
     */
    public static function getSubscribedServices(): array
    {
        return array_merge(
            parent::getSubscribedServices(),
            [
                DoctrineHelper::class,
                EntityPaginationNavigation::class,
                EntityRoutingHelper::class,
                MessageManager::class,
            ]
        );
    }
}
