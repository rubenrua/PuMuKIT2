<?php

namespace Pumukit\StatsBundle\Tests\Services;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Pumukit\SchemaBundle\Document\MultimediaObject;
use Pumukit\SchemaBundle\Document\Tag;
use Pumukit\SchemaBundle\Document\Track;
use Pumukit\StatsBundle\Services\StatsService;
use Pumukit\StatsBundle\Document\ViewsLog;
use Pumukit\SchemaBundle\Document\Series;

class StatsServiceTest extends WebTestCase
{
    private $dm;
    private $repo;
    private $factoryService;
    private $viewsService;

    public function setUp()
    {
        $options = array('environment' => 'test');
        static::bootKernel($options);

        $this->dm = static::$kernel->getContainer()
            ->get('doctrine_mongodb')->getManager();
        $this->repo = $this->dm
            ->getRepository('PumukitStatsBundle:ViewsLog');
        $this->factoryService = static::$kernel->getContainer()
            ->get('pumukitschema.factory');
        $this->viewsService = static::$kernel->getContainer()
            ->get('pumukit_stats.stats');

        $this->dm->getDocumentCollection('PumukitStatsBundle:ViewsLog')
            ->remove(array());
        $this->dm->getDocumentCollection('PumukitStatsBundle:ViewsAggregation')
            ->remove(array());
        $this->dm->getDocumentCollection(MultimediaObject::class)
            ->remove(array());
        $this->dm->getDocumentCollection(Series::class)
            ->remove(array());
        $this->dm->getDocumentCollection(Tag::class)
            ->remove(array());
    }

    private function logView($when, MultimediaObject $multimediaObject, Track $track = null)
    {
        $log = new ViewsLog('/', '8.8.8.8', 'test', '', $multimediaObject->getId(), $multimediaObject->getSeries()->getId(), null);
        $log->setDate($when);
        $multimediaObject->incNumview();
        $this->dm->persist($log);
        $this->dm->persist($multimediaObject);
        $this->dm->flush();

        return $log;
    }

    private function initContext()
    {
        $series = $this->factoryService->createSeries();
        $list = array();
        $list[1] = $this->factoryService->createMultimediaObject($series);
        $list[2] = $this->factoryService->createMultimediaObject($series);
        $list[3] = $this->factoryService->createMultimediaObject($series);
        $list[4] = $this->factoryService->createMultimediaObject($series);
        $list[5] = $this->factoryService->createMultimediaObject($series);

        foreach ($list as $i => $mm) {
            $mm->setStatus(MultimediaObject::STATUS_PUBLISHED);
            $this->dm->persist($mm);
        }
        $this->dm->flush();

        $this->logView(new \DateTime('now'), $list[1]);
        $this->logView(new \DateTime('now'), $list[3]);
        $this->logView(new \DateTime('now'), $list[3]);
        $this->logView(new \DateTime('now'), $list[3]);
        $this->logView(new \DateTime('now'), $list[2]);
        $this->logView(new \DateTime('now'), $list[2]);

        $this->logView(new \DateTime('-10 days'), $list[4]);
        $this->logView(new \DateTime('-10 days'), $list[4]);
        $this->logView(new \DateTime('-10 days'), $list[4]);
        $this->logView(new \DateTime('-10 days'), $list[4]);

        $this->logView(new \DateTime('-20 days'), $list[5]);
        $this->logView(new \DateTime('-20 days'), $list[5]);
        $this->logView(new \DateTime('-20 days'), $list[5]);
        $this->logView(new \DateTime('-20 days'), $list[5]);
        $this->logView(new \DateTime('-20 days'), $list[5]);

        $this->viewsService->aggregateViewsLog();

        $list[1]->setTitle('OTHER MMOBJ');

        return $list;
    }

    private function initTags($list)
    {
        $globalTag = new Tag();
        $globalTag->setCod('tv');
        $this->dm->persist($globalTag);

        $tags = array();
        foreach ($list as $i => $mm) {
            $tag = new Tag();
            $tag->setCod($i);
            $this->dm->persist($tag);
            $tags[$i] = $tag;
        }
        $this->dm->flush();

        foreach ($list as $i => $mm) {
            $mm->addTag($globalTag);
            $mm->addTag($tags[$i]);
            $this->dm->persist($mm);
        }
        $this->dm->flush();
    }

    public function tearDown()
    {
        $this->dm->close();
        $this->dm = null;
        $this->repo = null;
        $this->factoryService = null;
        $this->viewsService = null;
        gc_collect_cycles();
        parent::tearDown();
    }

    public function testSimpleStatsService()
    {
        $list = $this->initContext();

        $service = new StatsService($this->dm);
        $mv = $service->getMostViewed(array(), 1, 1);
        $this->assertEquals(1, count($mv));
        $this->assertEquals($mv, array($list[3]));

        $mv = $service->getMostViewed(array(), 30, 1);
        $this->assertEquals($mv, array($list[5]));

        $mv = $service->getMostViewed(array(), 1, 3);
        $this->assertEquals($mv, array($list[3], $list[2], $list[1]));

        $mv = $service->getMostViewed(array(), 30, 3);
        $this->assertEquals($mv, array($list[5], $list[4], $list[3]));

        $mv = $service->getMostViewed(array(), 30, 30);
        $this->assertEquals(5, count($mv));
        $this->assertEquals($mv, array($list[5], $list[4], $list[3], $list[2], $list[1]));
    }

    public function testStatsServiceWithBlockedVideos()
    {
        $list = $this->initContext();
        $this->initTags($list);

        $service = new StatsService($this->dm);
        $mv = $service->getMostViewed(array('tv'), 30, 3);
        $this->assertEquals($mv, array($list[5], $list[4], $list[3]));

        $mm = $list[5];
        foreach ($mm->getTags() as $tag) {
            $mm->removeTag($tag);
        }
        $this->dm->persist($mm);
        $this->dm->flush();

        $mv = $service->getMostViewed(array('tv'), 30, 3);
        $this->assertEquals($mv, array($list[4], $list[3], $list[2]));
    }

    public function testStatsServiceWithTags()
    {
        $list = $this->initContext();
        $this->initTags($list);

        $service = new StatsService($this->dm);

        $mv = $service->getMostViewed(array('1'), 30, 30);
        $this->assertEquals($mv, array($list[1]));

        $mv = $service->getMostViewed(array('11'), 30, 30);
        $this->assertEquals($mv, array());

        $mv = $service->getMostViewed(array('1'), 1, 3);
        $this->assertEquals($mv, array($list[1]));
    }

    public function testStatsServiceUsingFilters()
    {
        $list = $this->initContext();
        $this->initTags($list);

        $filter = $this->dm->getFilterCollection()->enable('frontend');
        $filter->setParameter('pub_channel_tag', '1');

        $this->dm->getFilterCollection()->enable('personal');

        $service = new StatsService($this->dm);

        $mv = $service->getMostViewedUsingFilters(30, 30);
        $this->assertEquals($mv, array($list[1]));
    }

    public function testGetMmobjsMostViewedByRange()
    {
        $list = $this->initContext();
        $this->initTags($list);
        $service = new StatsService($this->dm);
        //Maps the list to give an output similar to function
        $listMapped = array_map(function ($a) {
            return array(
                'mmobj' => $a,
                'num_viewed' => $a->getNumview(),
            );
        }, $list);
        //Sorts by least viewed
        usort($listMapped, function ($a, $b) {
            return $a['num_viewed'] > $b['num_viewed'];
        });

        list($mostViewed, $total) = $service->getMmobjsMostViewedByRange(array(), array('sort' => 1));

        $this->assertEquals($listMapped, $mostViewed);

        //Sorts by most viewed
        usort($listMapped, function ($a, $b) {
            return $a['num_viewed'] < $b['num_viewed'];
        });

        list($mostViewed, $total) = $service->getMmobjsMostViewedByRange();
        $this->assertEquals($listMapped, $mostViewed);
        $this->assertEquals($total, count($listMapped));

        list($mostViewed, $total) = $service->getMmobjsMostViewedByRange(array('title.en' => 'OTHER MMOBJ'));
        $this->assertEquals(array($listMapped[4]), $mostViewed);
        $this->assertEquals($total, 1);

        list($mostViewed, $total) = $service->getMmobjsMostViewedByRange(array(), array('limit' => 0));
        $this->assertEquals(array(), $mostViewed);
        $this->assertEquals($total, 5);
        list($mostViewed, $total) = $service->getMmobjsMostViewedByRange(array('not_a_parameter' => 'not_a_value'));
        $this->assertEquals($total, 0);
        list($mostViewed, $total) = $service->getMmobjsMostViewedByRange(array('title.en' => 'New'), array('limit' => 2, 'from_date' => new \DateTime('-11 days')));
        $this->assertEquals(array($listMapped[1], $listMapped[2]), $mostViewed);
        $this->assertEquals(4, $total);
        list($mostViewed, $total) = $service->getMmobjsMostViewedByRange(array('title.en' => 'New'), array('limit' => 2, 'from_date' => new \DateTime('-11 days'), 'page' => 1));
        $this->assertEquals(array($listMapped[3], array('mmobj' => $list[3], 'num_viewed' => 0)), $mostViewed);
        $this->assertEquals(4, $total);

        list($mostViewed, $total) = $service->getMmobjsMostViewedByRange(array(), array('from_date' => new \DateTime('-21 days'), 'to_date' => new \DateTime('-9 days')));

        $this->assertEquals(array($listMapped[0], $listMapped[1]), array_slice($mostViewed, 0, 2));
        $this->assertEquals(0, $mostViewed[2]['num_viewed']);
        $this->assertEquals(0, $mostViewed[3]['num_viewed']);
        $this->assertEquals(0, $mostViewed[4]['num_viewed']);
        $this->assertEquals(5, $total);
    }
}
