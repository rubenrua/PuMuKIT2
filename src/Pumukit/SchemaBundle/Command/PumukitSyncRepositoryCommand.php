<?php

namespace Pumukit\SchemaBundle\Command;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Pumukit\SchemaBundle\Document\MultimediaObject;
use Pumukit\EncoderBundle\Document\Job;
use Pumukit\SchemaBundle\Document\Tag;
use Pumukit\SchemaBundle\Document\Broadcast;
use Pumukit\SchemaBundle\Document\Role;

class PumukitSyncRepositoryCommand extends ContainerAwareCommand
{
    private $dm;
    private $mmRepo;

    protected function configure()
    {
        $this
            ->setName('pumukit:sync:repository')
            ->setDescription('Sync denormalized repository')
            ->setHelp(<<<'EOT'
Denormalize the database is necessary to increase the performance of the app. This command syncs denormalized repository, for instance:

 * Sync number of multimedia object in tags (tags.number_multimedia_objects).

EOT
          );
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->dm = $this->getContainer()->get('doctrine_mongodb')->getManager();

        $this->mmRepo = $this->dm->getRepository(MultimediaObject::class);

        $this->syncTags($input, $output);
        $this->syncNumberMultimediaObjectsOnBroadcast($input, $output);
        $this->syncNumberPeopleInMultimediaObjectsOnRoles($input, $output);
        $this->syncJobsInMultimediaObjectsProperties($input, $output);
    }

    private function syncJobsInMultimediaObjectsProperties(InputInterface $input, OutputInterface $output)
    {
        $jobColl = $this->dm->getDocumentCollection(Job::class);
        $mmObjColl = $this->dm->getDocumentCollection(MultimediaObject::class);

        $jobsPending = 0;
        $jobsExecuting = 0;

        $jobsByStatus = $jobColl->aggregate(array(
            array('$group' => array('_id' => '$status', 'count' => array('$sum' => 1))),
        ), array('cursor' => array()));
        foreach ($jobsByStatus as $jg) {
            if (in_array($jg['_id'], array(Job::STATUS_PAUSED, Job::STATUS_WAITING))) {
                $jobsPending += $jg['count'];
            } elseif (Job::STATUS_EXECUTING == $jg['_id']) {
                $jobsPending = $jg['count'];
            }
        }

        $jobsPendingInMmObjResult = $mmObjColl->aggregate(
            array(
                array('$unwind' => '$properties.pending_jobs'),
                array('$group' => array('_id' => null, 'count' => array('$sum' => 1))),
            ),
            array('cursor' => array())
        )->toArray();

        $jobsPendingInMmObj = $jobsPendingInMmObjResult[0]['count'] ?? 0;

        $jobsExecutingInMmObjResult = $mmObjColl->aggregate(array(
            array('$unwind' => '$properties.executing_jobs'),
            array('$group' => array('_id' => null, 'count' => array('$sum' => 1))),
        ), array('cursor' => array()))->toArray();

        $jobsExecutingInMmObj = $jobsExecutingInMmObjResult[0]['count'] ?? 0;

        if ($jobsPending != $jobsPendingInMmObj && 0 != $jobsPendingInMmObj) {
            $this->cleanJobsProperties('pending', $output);
        }

        if ($jobsExecuting != $jobsExecutingInMmObj && 0 != $jobsExecutingInMmObj) {
            $this->cleanJobsProperties('executing', $output);
        }
    }

    private function cleanJobsProperties($type, OutputInterface $output)
    {
        switch ($type) {
        case 'pending':
            $statuses = array(Job::STATUS_PAUSED, Job::STATUS_WAITING);
            break;
        case 'executing':
            $statuses = array(Job::STATUS_EXECUTING);
            break;
        default:
            throw new \InvalidArgumentException('type argument should be "pending" or "executing". Not'.$type);
        }

        $jobRepo = $this->dm->getRepository(Job::class);
        $mmObjRepo = $this->dm->getRepository(MultimediaObject::class);

        $pendingJobsId = $jobRepo->createQueryBuilder()
                       ->hydrate(false)
                       ->select('_id')
                       ->field('status')->in($statuses)
                       ->getQuery()
                       ->execute()
                       ->toArray();

        $qb = $mmObjRepo->createStandardQueryBuilder()
            ->field('properties.'.$type.'_jobs')->exists(true);

        if ($pendingJobsId) {
            $qb->field('properties.'.$type.'_jobs')->notIn(array_keys($pendingJobsId));
        }

        $mms = $qb->getQuery()
             ->execute();

        foreach ($mms as $multimediaObject) {
            $output->writeln('Fixing '.$type.'_jobs of multimedia object '.$multimediaObject->getId());
            $multimediaObject->removeProperty($type.'_jobs');
        }

        $this->dm->flush();
    }

    private function syncTags(InputInterface $input, OutputInterface $output)
    {
        $tagRepo = $this->dm->getRepository(Tag::class);
        $tagColl = $this->dm->getDocumentCollection(Tag::class);
        $mmColl = $this->dm->getDocumentCollection(MultimediaObject::class);

        $tagsInMMAggResult = $mmColl->aggregate(array(
            array('$match' => array(
                'type' => ['$ne' => MultimediaObject::TYPE_LIVE],
                'status' => array('$ne' => MultimediaObject::STATUS_PROTOTYPE,
                ),
            )),
            array('$project' => array('_id' => '$tags.cod')),
            array('$unwind' => '$_id'),
            array('$group' => array('_id' => '$_id', 'count' => array('$sum' => 1))),
        ), array('cursor' => array()));

        $tagsInMM = array();
        foreach ($tagsInMMAggResult as $i) {
            $tagsInMM[$i['_id']] = $i['count'];
        }

        $tagParentsAggResult = $tagColl->aggregate(array(
            array('$group' => array('_id' => '$parent', 'count' => array('$sum' => 1))),
        ), array('cursor' => array()));

        $tagParents = array();
        foreach ($tagParentsAggResult as $i) {
            $key = (string) $i['_id']['$id'];
            $tagParents[$key] = $i['count'];
        }

        $tags = $tagRepo->findAll();
        foreach ($tags as $tag) {
            $countMms = isset($tagsInMM[$tag->getCod()]) ? $tagsInMM[$tag->getCod()] : 0;
            $numOfChildren = isset($tagParents[$tag->getId()]) ? $tagParents[$tag->getId()] : 0;
            $output->writeln(sprintf('%s: %d mmobj and %d children', $tag->getCod(), $countMms, $numOfChildren));
            $tag->setNumberMultimediaObjects($countMms);
            $tag->setNumberOfChildren($numOfChildren);
        }
        $this->dm->flush();
    }

    /**
     * @deprecated in version 2.3
     */
    private function syncNumberMultimediaObjectsOnBroadcast(InputInterface $input, OutputInterface $output)
    {
        $broadcastRepo = $this->getContainer()->get('doctrine_mongodb')->getRepository(Broadcast::class);

        $output->writeln(' ');

        $broadcasts = $broadcastRepo->findAll();
        foreach ($broadcasts as $broadcast) {
            $mms = $this->mmRepo->findByBroadcast($broadcast);
            $output->writeln($broadcast->getName().': '.count($mms));
            $broadcast->setNumberMultimediaObjects(count($mms));
            $this->dm->persist($broadcast);
        }
        $this->dm->flush();
    }

    private function syncNumberPeopleInMultimediaObjectsOnRoles(InputInterface $input, OutputInterface $output)
    {
        $rolesRepo = $this->getContainer()->get('doctrine_mongodb')->getRepository(Role::class);

        $output->writeln(' ');

        $roles = $rolesRepo->findAll();
        foreach ($roles as $role) {
            $people = $this->mmRepo->findPeopleWithRoleCode($role->getCod());
            $output->writeln($role->getName().': '.count($people));
            $role->setNumberPeopleInMultimediaObject(count($people));
            $this->dm->persist($role);
        }
        $this->dm->flush();
    }
}
