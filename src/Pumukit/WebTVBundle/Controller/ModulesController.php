<?php

namespace Pumukit\WebTVBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Pumukit\CoreBundle\Controller\WebTVControllerInterface;
use Pumukit\SchemaBundle\Document\MultimediaObject;
use Pumukit\SchemaBundle\Document\Series;
use Pumukit\SchemaBundle\Document\Tag;

/**
 * Class ModulesController.
 */
class ModulesController extends Controller implements WebTVControllerInterface
{
    /**
     * @Template("PumukitWebTVBundle:Modules:widget_media.html.twig")
     *
     * @param string $design
     *
     * @return array
     */
    public function mostViewedAction($design = 'horizontal')
    {
        $dm = $this->get('doctrine_mongodb')->getManager();

        $limit = $this->container->getParameter('limit_objs_mostviewed');
        $showLastMonth = $this->container->getParameter('show_mostviewed_lastmonth');
        $translator = $this->get('translator');

        if ($showLastMonth) {
            $objects = $this->get('pumukit_stats.stats')->getMostViewedUsingFilters(30, $limit);
            $title = $translator->trans('Most viewed on the last month');
        } else {
            $objects = $dm->getRepository(MultimediaObject::class)->findStandardBy([], ['numview' => -1], $limit, 0);
            $title = $translator->trans('Most viewed');
        }

        return [
            'design' => $design,
            'objects' => $objects,
            'objectByCol' => $this->container->getParameter('mostviewed.objects_by_col'),
            'title' => $title,
            'class' => 'mostviewed',
            'show_info' => true,
            'show_more' => false,
        ];
    }

    /**
     * Returns all videos with PUDENEW tag.
     *
     * @Template("PumukitWebTVBundle:Modules:widget_media.html.twig")
     *
     * @return array
     *
     * @throws \Exception
     */
    public function highlightAction()
    {
        if (!$this->container->getParameter('show_latest_with_pudenew')) {
            throw new \Exception('Show latest with pudenew parameters must be true to use this module');
        }

        $translator = $this->get('translator');
        $title = $translator->trans('Hightlight');

        $limit = $this->container->getParameter('limit_objs_hightlight');

        $last = $this->get('pumukitschema.announce')->getLast($limit, true);

        return [
            'objects' => $last,
            'objectByCol' => $this->container->getParameter('hightlight.objects_by_col'),
            'class' => 'highlight',
            'title' => $title,
            'show_info' => false,
            'show_more' => false,
        ];
    }

    /**
     * Returns all videos without PUDENEW tag.
     *
     * @Template("PumukitWebTVBundle:Modules:widget_media.html.twig")
     *
     * @param string $design
     *
     * @return array
     */
    public function recentlyAddedWithoutHighlightAction($design = 'horizontal')
    {
        $translator = $this->get('translator');
        $title = $translator->trans('Recently added');
        $dm = $this->get('doctrine_mongodb.odm.document_manager');

        $limit = $this->container->getParameter('limit_objs_recentlyadded');

        $last = $dm->getRepository(MultimediaObject::class)->findStandardBy(
            ['tags.cod' => ['$ne' => 'PUDENEW']],
            [
                'public_date' => -1,
            ],
            $limit,
            0
        );

        return [
            'design' => $design,
            'objects' => $last,
            'objectByCol' => $this->container->getParameter('recentlyadded.objects_by_col'),
            'title' => $title,
            'class' => 'recently',
            'show_info' => true,
            'show_more' => false,
        ];
    }

    /**
     * Returns all videos without PUDENEW tag.
     *
     * @Template("PumukitWebTVBundle:Modules:widget_media.html.twig")
     *
     * @param string $design
     *
     * @return array
     */
    public function recentlyAddedAllAction($design = 'horizontal')
    {
        $translator = $this->get('translator');
        $title = $translator->trans('Recently added');
        $dm = $this->get('doctrine_mongodb.odm.document_manager');

        $limit = $this->container->getParameter('limit_objs_recentlyadded');

        $last = $dm->getRepository(MultimediaObject::class)->findStandardBy(
            [],
            [
                'public_date' => -1,
            ],
            $limit,
            0
        );

        return [
            'design' => $design,
            'objects' => $last,
            'objectByCol' => $this->container->getParameter('recentlyadded.objects_by_col'),
            'title' => $title,
            'class' => 'recently',
            'show_info' => true,
            'show_more' => false,
        ];
    }

    /**
     * @Template("PumukitWebTVBundle:Modules:widget_stats.html.twig")
     *
     * @return array
     */
    public function statsAction()
    {
        $mmRepo = $this->get('doctrine_mongodb')->getRepository(MultimediaObject::class);
        $seriesRepo = $this->get('doctrine_mongodb')->getRepository(Series::class);

        $counts = [
            'series' => $seriesRepo->countPublic(),
            'mms' => $mmRepo->count(),
            'hours' => $mmRepo->countDuration(),
        ];

        return ['counts' => $counts];
    }

    /**
     * @Template("PumukitWebTVBundle:Modules:widget_breadcrumb.html.twig")
     */
    public function breadcrumbsAction()
    {
        $breadcrumbs = $this->get('pumukit_web_tv.breadcrumbs');

        return ['breadcrumbs' => $breadcrumbs->getBreadcrumbs()];
    }

    /**
     * @Template("PumukitWebTVBundle:Modules:widget_language.html.twig")
     */
    public function languageAction()
    {
        $array_locales = $this->container->getParameter('pumukit.locales');
        if (count($array_locales) <= 1) {
            return new Response('');
        }

        return ['languages' => $array_locales];
    }

    /**
     * @Template("PumukitWebTVBundle:Modules:widget_categories.html.twig")
     *
     * @param Request $request
     * @param         $title
     * @param         $class
     * @param         $categories
     * @param int     $cols
     *
     * @return array
     */
    public function categoriesAction(Request $request, $title, $class, $categories, $cols = 6)
    {
        if (!$categories) {
            throw new NotFoundHttpException('Categories not found');
        }

        $dm = $this->get('doctrine.odm.mongodb.document_manager');

        if (is_array($categories)) {
            $tags = $dm->createQueryBuilder(Tag::class)
                ->field('cod')->in($categories)
                ->field('display')->equals(true)
                ->sort('title.'.$request->getLocale(), 1)
                ->getQuery()
                ->execute();
        } else {
            $tag = $dm->getRepository(Tag::class)->findOneBy(array(
                'cod' => $categories,
            ));

            if (!$tag) {
                throw new NotFoundHttpException('Category not found');
            }

            $tags = $tag->getChildren();
        }

        return [
            'objectByCol' => $cols,
            'objects' => $tags,
            'objectsData' => $categories,
            'title' => $title,
            'class' => $class,
        ];
    }

    /**
     * This module was create to keep BC. Uses vertical design by default.
     * Returns:
     * - showPudenew = true => Only videos with PUDENEW tag and announce property true
     * - showPudenew = false => Returns all videos.
     *
     * @Template("PumukitWebTVBundle:Modules:widget_media.html.twig")
     *
     * @param string $design
     *
     * @return array
     */
    public function legacyRecentlyAdded($design = 'vertical')
    {
        $translator = $this->get('translator');
        $title = $translator->trans('Recently added');

        $limit = $this->container->getParameter('limit_objs_recentlyadded');

        $showPudenew = $this->container->getParameter('show_latest_with_pudenew');
        $last = $this->get('pumukitschema.announce')->getLast($limit, $showPudenew);

        return [
            'design' => $design,
            'objects' => $last,
            'objectByCol' => $this->container->getParameter('recentlyadded.objects_by_col'),
            'title' => $title,
            'class' => 'recently',
            'show_info' => true,
            'show_more' => false,
        ];
    }

    /**
     * This module represents old categories block of PuMuKIT. Remember fix responsive design ( depends of height of images ).
     *
     * @Template("PumukitWebTVBundle:Modules:widget_block_categories.html.twig")
     *
     * @return array
     */
    public function legacyCategoriesAction()
    {
        return [];
    }

    public static $menuResponse = null;
    private $menuTemplate = 'PumukitWebTVBundle:Modules:widget_menu.html.twig';

    /**
     * This module represents old menu block of PuMuKIT ( vertical menu ). This design is just bootstrap panel example.
     *
     * @Template("PumukitWebTVBundle:Modules:widget_menu.html.twig")
     *
     * @return Response|null
     *
     * @throws \Exception
     */
    public function legacyMenuAction()
    {
        if (self::$menuResponse) {
            return self::$menuResponse;
        }
        $params = $this->getLegacyMenuElements();
        self::$menuResponse = $this->render($this->menuTemplate, $params);

        return self::$menuResponse;
    }

    /**
     * @return array
     *
     * @throws \Exception
     */
    private function getLegacyMenuElements()
    {
        $menuService = $this->get('pumukit_web_tv.menu_service');
        list($events, $channels, $liveEventTypeSession) = $menuService->getMenuEventsElement();
        $selected = $this->get('request_stack')->getMasterRequest()->get('_route');
        $homeTitle = $this->container->getParameter('menu.home_title');
        $announcesTitle = $this->container->getParameter('menu.announces_title');
        $searchTitle = $this->container->getParameter('menu.search_title');
        $catalogueTitle = $this->container->getParameter('menu.mediateca_title');
        $categoriesTitle = $this->container->getParameter('menu.categories_title');

        return [
            'events' => $events,
            'channels' => $channels,
            'type' => $liveEventTypeSession,
            'menu_selected' => $selected,
            'home_title' => $homeTitle,
            'announces_title' => $announcesTitle,
            'search_title' => $searchTitle,
            'catalogue_title' => $catalogueTitle,
            'categories_title' => $categoriesTitle,
        ];
    }
}
