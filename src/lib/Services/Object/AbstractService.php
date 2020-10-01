<?php
/**
 * This file is part of the Passwords App
 * created by Marius David Wieschollek
 * and licensed under the AGPL.
 */

namespace OCA\Passwords\Services\Object;

use Exception;
use OCA\Passwords\Db\AbstractMapper;
use OCA\Passwords\Db\EntityInterface;
use OCA\Passwords\Helper\Uuid\UuidHelper;
use OCA\Passwords\Hooks\Manager\HookManager;
use OCA\Passwords\Services\EnvironmentService;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\Entity;
use OCP\AppFramework\Db\MultipleObjectsReturnedException;
use OCP\EventDispatcher\IEventDispatcher;

/**
 * Class AbstractService
 *
 * @package OCA\Passwords\Services\Object
 */
abstract class AbstractService {

    /**
     * @var string|null
     */
    protected ?string $userId;

    /**
     * @var HookManager
     */
    protected HookManager $hookManager;

    /**
     * @var UuidHelper
     */
    protected UuidHelper $uuidHelper;

    /**
     * @var IEventDispatcher
     */
    protected IEventDispatcher $eventDispatcher;

    /**
     * @var EnvironmentService
     */
    protected EnvironmentService $environment;

    /**
     * @var string
     */
    protected string $class;

    /**
     * @var AbstractMapper
     */
    protected AbstractMapper $mapper;

    /**
     * AbstractService constructor.
     *
     * @param UuidHelper         $uuidHelper
     * @param IEventDispatcher   $eventDispatcher
     * @param HookManager        $hookManager
     * @param EnvironmentService $environment
     */
    public function __construct(
        UuidHelper $uuidHelper,
        IEventDispatcher $eventDispatcher,
        HookManager $hookManager,
        EnvironmentService $environment
    ) {
        $this->userId          = $environment->getUserId();
        $this->environment     = $environment;
        $this->hookManager     = $hookManager;
        $this->uuidHelper      = $uuidHelper;
        $this->eventDispatcher = $eventDispatcher;
    }

    /**
     * @return EntityInterface[]
     */
    public function findDeleted(): array {
        return $this->mapper->findAllDeleted();
    }

    /**
     * @param string $userId
     *
     * @return EntityInterface[]
     * @throws Exception
     */
    public function findByUserId(string $userId): array {
        return $this->mapper->findAllByUserId($userId);
    }

    /**
     * @param string $uuid
     *
     * @return EntityInterface
     * @throws DoesNotExistException
     * @throws MultipleObjectsReturnedException
     */
    public function findByUuid(string $uuid) {
        return $this->mapper->findByUuid($uuid);
    }

    /**
     * @param EntityInterface $model
     *
     * @return mixed
     */
    abstract public function save(EntityInterface $model): EntityInterface;

    /**
     * @param EntityInterface $entity
     * @param array           $overwrites
     *
     * @return EntityInterface
     * @throws Exception
     */
    public function clone(EntityInterface $entity, array $overwrites = []): EntityInterface {
        if(get_class($entity) !== $this->class) throw new Exception('Invalid revision class given');
        $this->fireEvent('beforeCloned', $entity);
        $this->hookManager->emit($this->class, 'preClone', [$entity]);
        $clone = $this->cloneModel($entity, $overwrites);
        $this->fireEvent('cloned', $entity, $clone);
        $this->fireEvent('afterCloned', $entity, $clone);
        $this->hookManager->emit($this->class, 'postClone', [$entity, $clone]);

        return $clone;
    }

    /**
     * @param EntityInterface $entity
     *
     * @throws Exception
     */
    public function delete(EntityInterface $entity): void {
        if(get_class($entity) !== $this->class) throw new Exception('Invalid revision class given');
        $this->hookManager->emit($this->class, 'preDelete', [$entity]);
        $entity->setDeleted(true);
        $this->fireEvent('beforeDeleted', $entity);
        $this->save($entity);
        $this->fireEvent('deleted', $entity);
        $this->fireEvent('afterDeleted', $entity);
        $this->hookManager->emit($this->class, 'postDelete', [$entity]);
    }

    /**
     * @param EntityInterface|Entity $entity
     *
     * @throws Exception
     */
    public function destroy(EntityInterface $entity): void {
        if(get_class($entity) !== $this->class) throw new Exception('Invalid revision class given');
        $this->hookManager->emit($this->class, 'preDestroy', [$entity]);
        if(!$entity->isDeleted()) $this->delete($entity);
        $this->fireEvent('beforeDestroyed', $entity);
        $this->mapper->delete($entity);
        $this->fireEvent('destroyed', $entity);
        $this->fireEvent('afterDestroyed', $entity);
        $this->hookManager->emit($this->class, 'postDestroy', [$entity]);
    }

    /**
     * @param EntityInterface $original
     * @param array           $overwrites
     *
     * @return EntityInterface
     */
    protected function cloneModel(EntityInterface $original, array $overwrites = []): EntityInterface {
        $class  = get_class($original);
        $clone  = new $class;
        $fields = array_keys($clone->getFieldTypes());

        foreach($fields as $field) {
            if($field === 'id' || $field === 'uuid') continue;
            if(isset($overwrites[ $field ])) {
                $clone->setProperty($field, $overwrites[ $field ]);
            } else {
                $clone->setProperty($field, $original->getProperty($field));
            }
        }

        $clone->setCreated(time());
        $clone->setUpdated(time());

        return $clone;
    }

    /**
     * @param string $name
     * @param mixed  ...$arguments
     */
    protected function fireEvent(string $name, ...$arguments) {
        $object = substr($this->class, strrpos($this->class, '\\'));
        if(substr($name, 0, 6) === 'before') {
            $eventClassName = "\OCA\Passwords\Events\{$object}\{$object}".ucfirst(substr($name, 6));
        } else if(substr($name, 0, 5) === 'after') {
            $eventClassName = "\OCA\Passwords\Events\{$object}\{$object}".ucfirst(substr($name, 5));
        } else {
            $eventClassName = "\OCA\Passwords\Events\{$object}\{$object}".ucfirst($name);
        }

        if(class_exists($eventClassName)) {
            $eventClass = new $eventClassName(...$arguments);
            $this->eventDispatcher->dispatchTyped($eventClass);
        } else {
            \OC::$server->getLogger()->error('Missing Event: '.$eventClassName);
        }
    }
}