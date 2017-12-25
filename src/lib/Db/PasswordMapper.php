<?php
/**
 * Created by PhpStorm.
 * User: marius
 * Date: 26.08.17
 * Time: 20:34
 */

namespace OCA\Passwords\Db;

/**
 * Class PasswordMapper
 *
 * @package OCA\Passwords\Db
 */
class PasswordMapper extends AbstractMapper {

    const TABLE_NAME = 'passwords_entity_password';

    /**
     * @param string $parentUuid
     *
     * @return Password[]
     */
    public function getByFolder(string $parentUuid): array {
        $passwordsTable = '`*PREFIX*'.static::TABLE_NAME.'`';
        $revisionTable  = '`*PREFIX*'.PasswordRevisionMapper::TABLE_NAME.'`';

        $sql = 'SELECT '.$passwordsTable.'.* FROM '.$passwordsTable.
               ' INNER JOIN '.$revisionTable.' ON '.$passwordsTable.'.`revision` = '.$revisionTable.'.`uuid`'.
               ' WHERE '.$passwordsTable.'.`deleted` = 0 AND '.$passwordsTable.'.`user_id` = ?'.
               ' AND '.$revisionTable.'.`folder` = ? AND '.$revisionTable.'.`deleted` = 0 AND '.$revisionTable.
               '.`user_id` = ?';

        return $this->findEntities($sql, [$this->userId, $parentUuid, $this->userId]);
    }

    /**
     * @param string $tagUuid
     * @param bool   $includeHidden
     *
     * @return Password[]
     */
    public function getByTag(string $tagUuid, bool $includeHidden = false): array {
        $passwordsTable = '`*PREFIX*'.static::TABLE_NAME.'`';
        $relationTable  = '`*PREFIX*'.PasswordTagRelationMapper::TABLE_NAME.'`';

        $sql = 'SELECT '.$passwordsTable.'.* FROM '.$passwordsTable.
               ' INNER JOIN '.$relationTable.' ON '.$passwordsTable.'.`uuid` = '.$relationTable.'.`password`'.
               ' WHERE '.$passwordsTable.'.`deleted` = 0 AND '.$passwordsTable.'.`user_id` = ?'.
               ' AND '.$relationTable.'.`tag` = ? AND '.$relationTable.'.`deleted` = 0 AND '.$relationTable.
               '.`user_id` = ?';

        if(!$includeHidden) $sql .= ' AND '.$relationTable.'.`hidden` = 0';

        return $this->findEntities($sql, [$this->userId, $tagUuid, $this->userId]);
    }
}