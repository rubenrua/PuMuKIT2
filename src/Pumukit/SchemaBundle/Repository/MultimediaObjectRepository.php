<?php

namespace Pumukit\SchemaBundle\Repository;

use Doctrine\ODM\MongoDB\DocumentRepository;
use Pumukit\SchemaBundle\Document\Series;
use Pumukit\SchemaBundle\Document\MultimediaObject;

/**
 * MultimediaObjectRepository
 *
 * This class was generated by the Doctrine ORM. Add your own custom
 * repository methods below.
 */
class MultimediaObjectRepository extends DocumentRepository
{
    /**
   * Find all multimedia objects in a series with given status
   *
   * @param Series $series
   * @param array $status
   * @return ArrayCollection
   */
  public function findWithStatus(Series $series, array $status)
  {
      return $this->createQueryBuilder()
      ->field('series')->references($series)
      ->field('status')->in($status)
      ->getQuery()
      ->execute()
      ->sort(array('rank', 'desc'));
  }

  /**
   * Find multimedia object prototype
   *
   * @param Series $series
   * @param array $status
   * @return MultimediaObject
   */
  public function findPrototype(Series $series)
  {
      return $this->createQueryBuilder()
      ->field('series')->references($series)
      ->field('status')->equals(MultimediaObject::STATUS_PROTOTYPE)
      ->getQuery()
      ->getSingleResult();
  }

  /**
   * Find multimedia objects in a series
   * without the template (prototype)
   *
   * @param Series $series
   * @return ArrayCollection
   */
  public function findWithoutPrototype(Series $series)
  {
      return $this->createQueryBuilder()
      ->field('series')->references($series)
      ->field('status')->notEqual(MultimediaObject::STATUS_PROTOTYPE)
      ->getQuery()
      ->execute()
      ->sort(array('rank', 'desc'));
  }

  /**
   * TODO DOC.
   */
  public function findByPicId($picId)
  {
      return $this->createQueryBuilder()
      ->field('pics._id')->equals(new \MongoId($picId))
      ->getQuery()
      ->getSingleResult();
  }

  /**
   * Find multimedia objects by person id
   *
   * @param string $personId
   * @return ArrayCollection
   */
  public function findByPersonId($personId)
  {
      return $this->createQueryBuilder()
        ->field('people_in_multimedia_object.people._id')->equals(new \MongoId($personId))
        ->getQuery()
        ->execute();
  }

  /**
   * Find multimedia objects by person id
   * with given role
   *
   * @param string $personId
   * @param string $roleCod
   * @return ArrayCollection
   */
  public function findByPersonIdWithRoleCod($personId, $roleCod)
  {
      /* TODO - Fails in this case -> MultimediaObject with:
         Person 1 with Role 1
         Person 2 with Role 2
        
         findByPersonIdWithRoleCode(Person 1, Role 2)
         -> returns this MultimediaObject because it has a person
         with id 1 and has a person with role 2
       */
      return $this->createQueryBuilder()
        ->field('people_in_multimedia_object.people._id')->equals(new \MongoId($personId))
        ->field('people_in_multimedia_object.cod')->equals($roleCod)
        ->getQuery()
        ->execute();
  }

  /**
   * Find series by person
   *
   * @param string $person
   * @return ArrayCollection
   */
  public function findSeriesFieldByPerson($person)
  {
      return $this->createQueryBuilder()
        ->field('people_in_multimedia_object.people._id')->equals(new \MongoId($person->getId()))
        ->distinct('series')
        ->getQuery()
        ->execute();
  }
}
