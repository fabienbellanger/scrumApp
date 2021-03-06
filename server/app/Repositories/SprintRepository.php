<?php

    namespace App\Repositories;

    use DB;
    use TZ;
    use Exception;
    use App\Repositories\UserRepository;
    use App\Repositories\TaskRepository;

    class SprintRepository
    {
        /**
         * Liste des sprints
         *
         * @author Fabien Bellanger
         * @param int    $id     ID de l'utilisateur
         * @param string $filter Filtre {'all', 'finished', 'inProgress'}
         * @return array
         */
        public static function getSprints(int $id, string $filter = 'all'): array
        {
            // Liste des sprints
            // -----------------
            $sprints = self::getSprintsOfUser($id, $filter);

            if ($sprints)
            {
                // ------------------------------------------------------------------------------------------
                // TODO: Optimiser les deux fonctions car certainement requêtes faisant presque la même chose
                // ------------------------------------------------------------------------------------------

                // Informations sur les sprints
                // ----------------------------
                $sprintsInformation = self::getSprintsInformation($id, $filter);

                // Informations sur les tâches
                // ---------------------------
                $sprintsTasksWorked = self::getSprintsWorkedDurationOfUser($id, $filter);

                // Construction du tableau
                // -----------------------
                foreach ($sprints as $sprintId => $sprintData)
                {
                    if (array_key_exists($sprintId, $sprintsTasksWorked))
                    {
                        $sprintData = array_merge($sprintData, $sprintsTasksWorked[$sprintId]);
                    }

                    if (array_key_exists($sprintId, $sprintsInformation))
                    {
                        $sprintData = array_merge($sprintData, $sprintsInformation[$sprintId]);
                    }

                    $sprints[$sprintId] = $sprintData;
                }
            }

            // Objet => tableau
            // ----------------
            $sprints = array_values($sprints);

            return [
                'code'    => 200,
                'message' => 'Success',
                'data'    => $sprints,
            ];
        }

        /**
         * Récupération d'une tâche
         *
         * @author Fabien Bellanger
         * @param int $sprintId ID du sprint
         * @param int $taskId   ID de la tâche (default 0)
         * @return \stdClass
         */
        public static function getTask(int $sprintId, int $taskId): \stdClass
        {
            $taskId = intval($taskId);
            $query  = '
                SELECT *
                FROM task
                WHERE task.id = :taskId 
                    AND task.sprint_id = :sprintId';
            $tasks  = DB::select($query, ['taskId' => $taskId, 'sprintId' => $sprintId]);
            if (!$tasks || count($tasks) != 1)
            {
                return null;
            }

            return $tasks[0];
        }

        /**
         * Liste des sprints
         *
         * TODO: Utiliser la variable $userId
         *
         * @author Fabien Bellanger
         * @param int $userId   ID de l'utilisateur
         * @param int $sprintId ID du sprint
         * @return bool
         */
        public static function isSprintValid(int $userId, int $sprintId): bool
        {
            $query   = '
                SELECT sprint.id
                FROM sprint
                WHERE sprint.id = :sprintId';
            $results = DB::select($query, ['sprintId' => $sprintId]);

            if (!$results || count($results) != 1)
            {
                return false;
            }

            return true;
        }

        /**
         * ID de l'équipe
         *
         * @author Fabien Bellanger
         * @param int $sprintId ID du sprint
         * @return int
         */
        public static function getTeamId(int $sprintId): int
        {
            $query   = '
                SELECT sprint.team_id
                FROM sprint
                WHERE sprint.id = :sprintId';
            $results = DB::select($query, ['sprintId' => $sprintId]);
            if (!$results || count($results) != 1)
            {
                return 0;
            }

            return $results[0]->team_id;
        }

        /**
         * Liste des sprints d'un utilisateur
         *
         * @author Fabien Bellanger
         * @param int    $userId ID de l'utilisateur
         * @param string $filter Filtre {'all', 'finished', 'inProgress'}
         * @return array
         */
        public static function getSprintsOfUser(int $userId, string $filter): array
        {
            // Requête
            // -------
            $query = '
                SELECT DISTINCT
                    sprint.id                      AS sprintId,
                    sprint.name                    AS sprintName,
                    sprint.team_id                 AS teamId,
                    team.name                      AS teamName,
                    sprint.created_at              AS sprintCreatedAt,
                    sprint.started_at              AS sprintStartedAt,
                    sprint.delivered_at            AS sprintDeliveredAt,
                    sprint.finished_at             AS sprintFinishedAt,
                    SUM(task.initial_duration)     AS initialDuration,
                    SUM(task.remaining_duration)   AS remainingDuration
                FROM sprint
                    INNER JOIN team ON team.id = sprint.team_id
                    LEFT JOIN task ON sprint.id = task.sprint_id
                WHERE 
                    sprint.id IN (
                        SELECT DISTINCT sprint.id
                        FROM sprint
                            INNER JOIN team ON team.id = sprint.team_id
                            INNER JOIN team_member ON team.id = team_member.team_id
                        WHERE (team_member.user_id = :userId OR team.owner_id = :ownerId)
                    )';
            if ($filter == 'inProgress')
            {
                $query .= ' AND sprint.finished_at IS NULL';
            }
            elseif ($filter == 'finished')
            {
                $query .= ' AND sprint.finished_at IS NOT NULL';
            }
            $query .= ' GROUP BY sprint.id ORDER BY sprint.started_at ASC';

            $results = DB::select($query, ['userId' => $userId, 'ownerId' => $userId]);

            // Traitement
            // ----------
            $sprints  = [];
            $timezone = UserRepository::getTimezone();
            if ($results)
            {
                // Mise en forme des données
                // -------------------------
                foreach ($results as $line)
                {
                    $sprintId = $line->sprintId;

                    if (!isset($sprints[$sprintId]))
                    {
                        $sprints[$sprintId]['id']                = $sprintId;
                        $sprints[$sprintId]['name']              = $line->sprintName;
                        $sprints[$sprintId]['teamId']            = $line->teamId;
                        $sprints[$sprintId]['teamName']          = $line->teamName;
                        $sprints[$sprintId]['createdAt']         = TZ::getLocalDatetime2($timezone, $line->sprintCreatedAt, 'Y-m-d H:i:s');
                        $sprints[$sprintId]['finishedAt']        = TZ::getLocalDatetime2($timezone, $line->sprintFinishedAt, 'Y-m-d H:i:s');
                        $sprints[$sprintId]['startedAt']         = $line->sprintStartedAt;
                        $sprints[$sprintId]['deliveredAt']       = $line->sprintDeliveredAt;
                        $sprints[$sprintId]['initialDuration']   = floatval($line->initialDuration);
                        $sprints[$sprintId]['remainingDuration'] = floatval($line->remainingDuration);
                        $sprints[$sprintId]['progressPercent']   = ($line->initialDuration != 0)
                            ? round((($line->initialDuration - $line->remainingDuration) / $line->initialDuration) * 100, 0)
                            : 0;
                    }
                }
            }

            return $sprints;
        }

        /**
         * Informations sur les durées des sprints d'un utilisateur
         *
         * @author Fabien Bellanger
         * @param int    $userId ID de l'utilisateur
         * @param string $filter Filtre {'all', 'finished', 'inProgress'}
         * @return array
         */
        public static function getSprintsWorkedDurationOfUser(int $userId, string $filter): array
        {
            // Requête
            // -------
            $query = '
                SELECT
                    sprint.id                      AS sprintId,
                    task.id                        AS taskId,
                    task.added_after               AS taskAddedAfter,
                    SUM(task_user.worked_duration) AS workedDuration
                FROM sprint
                    INNER JOIN team ON team.id = sprint.team_id
                    LEFT JOIN task ON sprint.id = task.sprint_id
                    LEFT JOIN task_user ON task.id = task_user.task_id
                WHERE 
                    sprint.id IN (
                        SELECT DISTINCT sprint.id
                        FROM sprint
                            INNER JOIN team ON team.id = sprint.team_id
                            INNER JOIN team_member ON team.id = team_member.team_id
                        WHERE (team_member.user_id = :userId OR team.owner_id = :ownerId)
                    )';
            if ($filter == 'inProgress')
            {
                $query .= ' AND sprint.finished_at IS NULL';
            }
            elseif ($filter == 'finished')
            {
                $query .= ' AND sprint.finished_at IS NOT NULL';
            }
            $query .= ' GROUP BY task.id';
            $query .= ' ORDER BY task.name';

            $results = DB::select($query, ['userId' => $userId, 'ownerId' => $userId]);

            // Traitement
            // ----------
            $tasks = [];
            if ($results)
            {
                // Mise en forme des données
                // -------------------------
                foreach ($results as $line)
                {
                    $sprintId = $line->sprintId;
                    $taskId   = $line->taskId;

                    // Initialisation
                    // --------------
                    if (!isset($tasks[$sprintId]))
                    {
                        $tasks[$sprintId]['workedDuration']      = 0;
                        $tasks[$sprintId]['addedWorkedDuration'] = 0;
                        $tasks[$sprintId]['tasksNumber']         = 0;
                        $tasks[$sprintId]['tasksAddedNumber']    = 0;
                    }

                    $tasks[$sprintId]['workedDuration'] += floatval($line->workedDuration);
                    $tasks[$sprintId]['tasksNumber']++;
                    if ($line->taskAddedAfter)
                    {
                        $tasks[$sprintId]['tasksAddedNumber']++;
                        $tasks[$sprintId]['addedWorkedDuration'] += floatval($line->workedDuration);
                    }
                }
            }

            return $tasks;
        }
        
        /**
         * Informations sur les sprints d'un utilisateur
         *
         * @author Fabien Bellanger
         * @param int    $userId ID de l'utilisateur
         * @param string $filter Filtre {'all', 'finished', 'inProgress'}
         * @return array
         */
        public static function getSprintsInformation(int $userId, string $filter): array
        {
            // Requête
            // -------
            $query = '
                SELECT
                    sprint.id                       AS sprintId,
                    SUM(task_user.duration)         AS decrementedDuration,
                    SUM(task_user.worked_duration)  AS workedDuration,
                    COUNT(DISTINCT(task_user.date)) AS nbDates
                FROM sprint
                    INNER JOIN team ON team.id = sprint.team_id
                    LEFT JOIN task ON sprint.id = task.sprint_id
                    LEFT JOIN task_user ON task.id = task_user.task_id
                WHERE 
                    sprint.id IN (
                        SELECT DISTINCT sprint.id
                        FROM sprint
                            INNER JOIN team ON team.id = sprint.team_id
                            INNER JOIN team_member ON team.id = team_member.team_id
                        WHERE (team_member.user_id = :userId OR team.owner_id = :ownerId)
                    )';
            if ($filter == 'inProgress')
            {
                $query .= ' AND sprint.finished_at IS NULL';
            }
            elseif ($filter == 'finished')
            {
                $query .= ' AND sprint.finished_at IS NOT NULL';
            }
            $query .= ' GROUP BY sprint.id';

            $results = DB::select($query, ['userId' => $userId, 'ownerId' => $userId]);

            // Traitement
            // ----------
            $data = [];
            if ($results)
            {
                // Mise en forme des données
                // -------------------------
                foreach ($results as $line)
                {
                    $sprintId            = $line->sprintId;
                    $decrementedDuration = floatval($line->decrementedDuration);
                    $workedDuration      = floatval($line->workedDuration);
                    $nbDates             = floatval($line->nbDates);

                    // Initialisation
                    // --------------
                    if (!isset($data[$sprintId]))
                    {
                        $data[$sprintId]['decrementedDuration'] = 0;
                        $data[$sprintId]['workedDuration']      = 0;
                        $data[$sprintId]['nbDates']             = 0;
                    }

                    $data[$sprintId]['decrementedDuration'] += $decrementedDuration;
                    $data[$sprintId]['workedDuration']      += $workedDuration;
                    $data[$sprintId]['nbDates']             += $nbDates;
                }

                // Calcul du nombre d'heures décrémentées par jour
                // -----------------------------------------------
                foreach ($data as $id => $line)
                {
                    $data[$id]['decrementedDurationPerDay'] = ($line['nbDates'] != 0)
                        ? $line['decrementedDuration'] / $line['nbDates']
                        : 0;
                    $data[$id]['workedDurationPerDay'] = ($line['nbDates'] != 0)
                        ? $line['workedDuration'] / $line['nbDates']
                        : 0;
                }
            }

            return $data;
        }

        /**
         * Information d'un sprint
         *
         * @author Fabien Bellanger
         * @param int $userId   ID de l'utilisateur
         * @param int $sprintId ID du sprint
         * @return array
         */
        public static function getSprintInfo(int $userId, int $sprintId): array
        {
            $sprint = [];

            // 1. Récupération du sprint
            // -------------------------
            $query   = '
                SELECT sprint.name, sprint.created_at, sprint.updated_at, sprint.started_at, sprint.finished_at, sprint.delivered_at
                FROM sprint
                WHERE sprint.id = :sprintId';
            $results = DB::select($query, ['sprintId' => $sprintId]);
            if (!$results || count($results) != 1)
            {
                return [
                    'code'    => 404,
                    'message' => 'No sprint found',
                ];
            }

            // Timezone
            // --------
            $timezone = UserRepository::getTimezone();

            $sprint['id']          = $sprintId;
            $sprint['name']        = $results[0]->name;
            $sprint['createdAt']   = TZ::getLocalDatetime2($timezone, $results[0]->created_at, 'Y-m-d H:i:s');
            $sprint['updatedAt']   = TZ::getLocalDatetime2($timezone, $results[0]->updated_at, 'Y-m-d H:i:s');
            $sprint['startedAt']   = $results[0]->started_at;
            $sprint['deliveredAt'] = $results[0]->delivered_at;
            $sprint['finishedAt']  = TZ::getLocalDatetime2($timezone, $results[0]->finished_at, 'Y-m-d H:i:s');

            // 2. Récupération des tâches
            // --------------------------
            $query   = '
                SELECT
                    task.id                     AS taskId,
                    task.name                   AS taskName,
                    task.description            AS taskDescription,
                    task.type                   AS taskType,
                    task.initial_duration       AS taskInitialDuration,
                    task.remaining_duration     AS taskRemainingDuration,
                    task.user_id                AS taskUserId,
                    task.added_after            AS taskAddedAfter,
                    task.delivered_at           AS taskdeliveredAt,
                    task_user.id                AS taskPartId,
                    task_user.user_id           AS taskPartUserId,
                    task_user.duration          AS taskPartDuration,
                    task_user.worked_duration   AS taskPartWorkedDuration,
                    task_user.date              AS taskPartDate
                FROM task
                    LEFT JOIN task_user ON task_user.task_id = task.id
                WHERE task.sprint_id = :sprintId
                ORDER BY task.name ASC';
            $results = DB::select($query, ['sprintId' => $sprintId]);
            $tasks   = [];
            foreach ($results as $task)
            {
                $taskId     = $task->taskId;
                $taskPartId = $task->taskPartId;

                if (!array_key_exists($taskId, $tasks))
                {
                    $tasks[$taskId]['id']                = $taskId;
                    $tasks[$taskId]['name']              = $task->taskName;
                    $tasks[$taskId]['description']       = $task->taskDescription;
                    $tasks[$taskId]['type']              = $task->taskType;
                    $tasks[$taskId]['initialDuration']   = floatval($task->taskInitialDuration);
                    $tasks[$taskId]['remainingDuration'] = floatval($task->taskRemainingDuration);
                    $tasks[$taskId]['userId']            = $task->taskUserId;
                    $tasks[$taskId]['addedAfter']        = $task->taskAddedAfter;
                    $tasks[$taskId]['deliveredAt']       = $task->taskdeliveredAt;
                    $tasks[$taskId]['list']              = [];
                }

                if ($taskPartId && !array_key_exists($taskPartId, $tasks[$taskId]['list']))
                {
                    $tasks[$taskId]['list'][$taskPartId]['id']             = $taskPartId;
                    $tasks[$taskId]['list'][$taskPartId]['userId']         = $task->taskPartUserId;
                    $tasks[$taskId]['list'][$taskPartId]['duration']       = floatval($task->taskPartDuration);
                    $tasks[$taskId]['list'][$taskPartId]['workedDuration'] = floatval($task->taskPartWorkedDuration);
                    $tasks[$taskId]['list'][$taskPartId]['date']           = $task->taskPartDate; // TZ::getLocalDatetime2($timezone, $task->taskPartDate, 'Y-m-d');
                }
            }

            // 3. Récupération des utilisateurs
            // --------------------------------
            $users   = [];
            $query   = '
                SELECT DISTINCT 
                    users.id,
                    users.firstname,
                    users.lastname, 
                    users.email,
                    users.worked_hours_per_day,
                    users.group_id
                FROM task
                    INNER JOIN task_user ON task_user.task_id = task.id
                    INNER JOIN users ON users.id = task_user.user_id
                WHERE task.sprint_id = :sprintId';
            $results = DB::select($query, ['sprintId' => $sprintId]);
            if ($results && count($results) > 0)
            {
                foreach ($results as $user)
                {
                    if (!array_key_exists($user->id, $users))
                    {
                        $users[$user->id]['id']                = intval($user->id);
                        $users[$user->id]['firstname']         = $user->firstname;
                        $users[$user->id]['lastname']          = $user->lastname;
                        $users[$user->id]['email']             = $user->email;
                        $users[$user->id]['groupId']           = intval($user->group_id);
                        $users[$user->id]['workedHoursPerDay'] = intval($user->worked_hours_per_day);
                    }
                }
            }
            $sprint['users'] = $users;

            // 5. Objet => tableau
            // -------------------
            $tasks = array_values($tasks);
            foreach ($tasks as $index => $task)
            {
                $tasks[$index]['list'] = array_values($tasks[$index]['list']);
            }

            $sprint['tasks'] = $tasks;

            return [
                'code'    => 200,
                'message' => 'Success',
                'data'    => $sprint,
            ];
        }

        /**
         * Ajout / Modification d'une tâche
         *
         * @author Fabien Bellanger
         * @param int   $userId   ID de l'utilisateur
         * @param int   $sprintId ID du sprint
         * @param array $data     POST data
         * @param int   $taskId   ID de la tâche (default 0)
         * @return array
         */
        public static function editTask(int $userId, int $sprintId, array $data, int $taskId = 0): array
        {
            // 1. Vérification des données
            // ---------------------------
            if (!array_key_exists('name', $data) || !array_key_exists('duration', $data)
                || !array_key_exists('notPlanned', $data))
            {
                return [
                    'code'    => 500,
                    'message' => 'Bad data',
                ];
            }

            // 2. Sprint valide ?
            // ------------------
            if (!self::isSprintValid($userId, $sprintId))
            {
                return [
                    'code'    => 404,
                    'message' => 'No sprint found',
                ];
            }

            // 3. Traitement des données
            // -------------------------
            $data['applicationsIds'] = (!array_key_exists('applicationsIds', $data))
                ? []
                : $data['applicationsIds'];

            // 4. Enregistrement en base
            // -------------------------
            try
            {
                DB::transaction(function() use ($userId, $sprintId, $taskId, &$data) {
                    if ($taskId == 0)
                    {
                        // Création
                        // --------
                        self::addTask($userId, $sprintId, $data);
                    }
                    else
                    {
                        // Modification
                        // ------------
                        self::modifyTask($userId, $sprintId, $taskId, $data);
                    }
                });
            }
            catch (Exception $exception)
            {
                return [
                    'code'    => 500,
                    'message' => 'Internal error',
                ];
            }

            if (!isset($data['id']))
            {
                return [
                    'code'    => 500,
                    'message' => 'Internal error',
                ];
            }

            return [
                'code'    => 200,
                'message' => 'Success',
                'data'    => $data,
            ];
        }

        /**
         * Ajout d'une tâche
         *
         * @author Fabien Bellanger
         * @param int   $userId   ID de l'utilisateur
         * @param int   $sprintId ID du sprint
         * @param array $data     POST data (Référence)
         */
        static private function addTask(int $userId, int $sprintId, array &$data): void
        {
            // Ajout dans la table task
            // ------------------------
            $timezone  = UserRepository::getTimezone();
            $createdAt = TZ::getUTCDatetime2($timezone, date('Y-m-d H:i:s'), 'Y-m-d H:i:s');
            $taskData  = [
                'user_id'            => $userId,
                'sprint_id'          => $sprintId,
                'name'               => $data['name'],
                'description'        => ($data['description']) ? $data['description'] : null,
                'type'               => intval($data['type']),
                'initial_duration'   => floatval($data['duration']),
                'remaining_duration' => floatval($data['duration']),
                'added_after'        => intval($data['notPlanned']),
                'delivered_at'       => $data['deliveredAt'],
                'created_at'         => $createdAt,
                'updated_at'         => $createdAt,
            ];
            $taskId   = DB::table('task')->insertGetId($taskData);

            // Ajout dans la table task_application
            // ------------------------------------
            $taskApplicationData = [];
            $taskApplicationItem = ['task_id' => 0, 'application_id' => 0];
            foreach ($data['applicationsIds'] as $applicationId)
            {
                $taskApplicationItem['task_id']        = $taskId;
                $taskApplicationItem['application_id'] = $applicationId;

                $taskApplicationData[] = $taskApplicationItem;
            }
            DB::table('task_application')->insert($taskApplicationData);

            // Ajout de l'ID de la tâche
            // -------------------------
            $data['id'] = $taskId;
        }

        /**
         * Modification d'une tâche
         *
         * @author Fabien Bellanger
         * @param int   $userId   ID de l'utilisateur
         * @param int   $sprintId ID du sprint
         * @param int   $taskId   ID de la tâche
         * @param array $data     POST data (Référence)
         */
        static private function modifyTask(int $userId, int $sprintId, int $taskId, array &$data): void
        {
            // Récupération de la tâche
            // ------------------------
            //$task = self::getTask($sprintId, $taskId);

            // Mise à jour dans la table task
            // ------------------------------
            $timezone  = UserRepository::getTimezone();
            $updatedAt = TZ::getUTCDatetime2($timezone, date('Y-m-d H:i:s'), 'Y-m-d H:i:s');
            $taskData  = [
                'name'         => $data['name'],
                'description'  => ($data['description']) ? $data['description'] : null,
                'type'         => intval($data['type']),
                'added_after'  => intval($data['notPlanned']),
                'delivered_at' => $data['deliveredAt'],
                'updated_at'   => $updatedAt,
            ];
            DB::table('task')
              ->where('id', $taskId)
              ->update($taskData);

            // Suppression des éléments de la table task_application
            // -----------------------------------------------------
            DB::table('task_application')
              ->where('task_id', $taskId)
              ->delete();

            // Ajout dans la table task_application
            // ------------------------------------
            $taskApplicationData = [];
            $taskApplicationItem = ['task_id' => 0, 'application_id' => 0];
            foreach ($data['applicationsIds'] as $applicationId)
            {
                $taskApplicationItem['task_id']        = $taskId;
                $taskApplicationItem['application_id'] = $applicationId;

                $taskApplicationData[] = $taskApplicationItem;
            }
            DB::table('task_application')->insert($taskApplicationData);

            // Ajout de l'ID de la tâche
            // -------------------------
            $data['id'] = $taskId;
        }

        /**
         * Suppression d'une tâche
         *
         * @author Fabien Bellanger
         * @param int $userId   ID de l'utilisateur
         * @param int $sprintId ID du sprint
         * @param int $taskOd   ID de la tâche
         * @return array
         */
        static public function deleteTask(int $userId, int $sprintId, int $taskId): array
        {
            // 1. Sprint valide ?
            // ------------------
            if (!self::isSprintValid($userId, $sprintId))
            {
                return [
                    'code'    => 404,
                    'message' => 'No sprint found',
                ];
            }

            // 2. Suppression de la tâche et des données associées
            // ---------------------------------------------------
            $query = '
                DELETE FROM task
                WHERE task.id = :taskId 
                    AND task.sprint_id = :sprintId';
            DB::delete($query, ['taskId' => $taskId, 'sprintId' => $sprintId]);

            return [
                'code'    => 200,
                'message' => 'Success',
                'data'    => $taskId,
            ];
        }

        /**
         * Récupération d'une tâche
         *
         * @author Fabien Bellanger
         * @param int $userId   ID de l'utilisateur
         * @param int $sprintId ID du sprint
         * @param int $taskOd   ID de la tâche
         * @return array
         */
        static public function getTaskInfo(int $userId, int $sprintId, int $taskId): array
        {
            // 1. Tâche valide ?
            // -----------------
            $taskId = intval($taskId);
            if ($taskId == 0)
            {
                return [
                    'code'    => 404,
                    'message' => 'No task found',
                ];
            }

            // 2. Sprint valide ?
            // ------------------
            if (!self::isSprintValid($userId, $sprintId))
            {
                return [
                    'code'    => 404,
                    'message' => 'No sprint found',
                ];
            }

            // 3. Récupération de la tâche
            // ---------------------------
            $task = self::getTask($sprintId, $taskId);
            if (!$task)
            {
                return [
                    'code'    => 500,
                    'message' => 'Internal error',
                ];
            }
            $taskData = [
                'id'                => intval($task->id),
                'sprintId'          => intval($task->sprint_id),
                'name'              => $task->name,
                'description'       => $task->description,
                'type'              => $task->type,
                'initialDuration'   => floatval($task->initial_duration),
                'remainingDuration' => floatval($task->remaining_duration),
                'addedAfter'        => intval($task->added_after),
                'deliveredAt'       => $task->delivered_at,
                'applications'      => [],
            ];

            // 4. Récupération des applications
            // --------------------------------
            $query        = '
                SELECT application_id AS id
                FROM task_application
                WHERE task_id = :taskId';
            $applications = DB::select($query, ['taskId' => $taskId]);
            foreach ($applications as $application)
            {
                $taskData['applications'][] = $application->id;
            }

            return [
                'code'    => 200,
                'message' => 'Success',
                'data'    => $taskData,
            ];
        }

        /**
         * Récupération des paramètres d'un sprint
         *
         * @author Fabien Bellanger
         * @param int $userId   ID de l'utilisateur
         * @param int $sprintId ID du sprint
         * @return array
         */
        static public function getSprintParameters(int $id, int $sprintId): array
        {
            $sprint = [];

            // 1. Récupération du sprint
            // -------------------------
            $query = '
                SELECT 
                    sprint.name         AS sprintName,
                    sprint.started_at   AS startedAt,
                    sprint.delivered_at AS deliveredAt,
                    sprint.team_id      AS teamId,
                    team.name           AS teamName
                FROM sprint
                    INNER JOIN team ON team.id = sprint.team_id
                WHERE sprint.id = :sprintId';

            $results = DB::select($query, ['sprintId' => $sprintId]);
            if (!$results || count($results) != 1)
            {
                return [
                    'code'    => 404,
                    'message' => 'No sprint found',
                ];
            }
            $sprint['id']          = $sprintId;
            $sprint['name']        = $results[0]->sprintName;
            $sprint['startedAt']   = $results[0]->startedAt;
            $sprint['deliveredAt'] = $results[0]->deliveredAt;
            $sprint['teamName']    = $results[0]->teamName;

            // 2. Récupération des membres de l'équipe
            // ---------------------------------------
            $teamId = $results[0]->teamId;
            $users  = [];
            $query  = '
                SELECT
                    users.id,
                    users.firstname,
                    users.lastname,
                    users.email,
                    team_member.team_id
                FROM users
                    LEFT JOIN team_member ON team_member.user_id = users.id';

            $results = DB::select($query);
            if ($results && count($results) > 0)
            {
                foreach ($results as $user)
                {
                    if (!array_key_exists($user->id, $users))
                    {
                        $users[$user->id]['id']     = $user->id;
                        $users[$user->id]['email']  = $user->email;
                        $users[$user->id]['name']   = $user->firstname . ' ' . $user->lastname;
                        $users[$user->id]['inTeam'] = ($teamId == $user->team_id);
                    }
                    elseif (!$users[$user->id]['inTeam'])
                    {
                        // Si l'utilisateur n'est pas dans l'équipe, on fait le test
                        $users[$user->id]['inTeam'] = ($teamId == $user->team_id);
                    }
                }
            }
            $sprint['users'] = array_values($users);

            return [
                'code'    => 200,
                'message' => 'Success',
                'data'    => $sprint,
            ];
        }

        /**
         * Modification des paramètres d'un sprint
         *
         * @author Fabien Bellanger
         * @param int   $userId   ID de l'utilisateur
         * @param int   $sprintId ID du sprint
         * @param array $data     POST data (Référence)
         * @return array
         */
        static public function modifySprintParameters(int $userId, int $sprintId, array &$data): array
        {
            $sprintId = intval($sprintId);

            // Mise à jour dans la table sprint
            // --------------------------------
            $sprintData = [
                'name'         => $data['name'],
                'started_at'   => $data['startedAt'],
                'delivered_at' => $data['deliveredAt'],
            ];
            DB::table('sprint')
              ->where('id', $sprintId)
              ->update($sprintData);

            // Mise à jour des membres de l'équipe
            // -----------------------------------
            $usersInDB  = TeamRepository::getUsersIdFromSprint($sprintId);
            $newUsersId = $data['usersId'];
            $teamId     = self::getTeamId($sprintId);

            // Suppression des utilisateurs
            // ----------------------------
            $usersToDelete = [];
            foreach ($usersInDB as $userInDB)
            {
                if (!in_array($userInDB->id, $newUsersId))
                {
                    $usersToDelete[] = $userInDB->id;
                }
            }
            DB::table('team_member')
              ->where('team_id', $teamId)
              ->whereIn('user_id', $usersToDelete)
              ->delete();

            // Ajout des utilisateurs
            // ----------------------
            $usersToAdd  = [];
            $nbUsersInDB = count($usersInDB);
            foreach ($newUsersId as $newUserId)
            {
                $userPresent = false;
                $i           = 0;
                while (!($i == $nbUsersInDB || $userPresent))
                {
                    if ($newUserId == $usersInDB[$i]->id)
                    {
                        $userPresent = true;
                    }

                    $i++;
                }

                if (!$userPresent)
                {
                    array_push($usersToAdd, [
                        'team_id' => $teamId,
                        'user_id' => $newUserId,
                    ]);
                }
            }
            DB::table('team_member')->insert($usersToAdd);

            return ["id" => $sprintId];
        }

        /**
         * Information d'un sprint pour la création des task_user
         *
         * @author Fabien Bellanger
         * @param int    $userId   ID de l'utilisateur
         * @param int    $sprintId ID du sprint
         * @param string $date     Date
         * @return array
         */
        public static function getSprintManagement(int $userId, int $sprintId, string $date): array
        {
            $sprint = [];

            // 1. Récupération du sprint
            // -------------------------
            $query   = '
                SELECT
                    sprint.name,
                    sprint.team_id,
                    sprint.created_at,
                    sprint.updated_at,
                    sprint.started_at,
                    sprint.delivered_at,
                    sprint.finished_at
                FROM sprint
                WHERE sprint.id = :sprintId';
            $results = DB::select($query, ['sprintId' => $sprintId]);
            if (!$results || count($results) != 1)
            {
                return [
                    'code'    => 404,
                    'message' => 'No sprint found',
                ];
            }

            // Timezone
            // --------
            $timezone = UserRepository::getTimezone();
            
            $sprint['date']        = $date;
            $sprint['id']          = $sprintId;
            $sprint['name']        = $results[0]->name;
            $sprint['teamId']      = $results[0]->team_id;
            $sprint['createdAt']   = TZ::getLocalDatetime2($timezone, $results[0]->created_at, 'Y-m-d H:i:s');
            $sprint['updatedAt']   = TZ::getLocalDatetime2($timezone, $results[0]->updated_at, 'Y-m-d H:i:s');
            $sprint['startedAt']   = $results[0]->started_at;
            $sprint['deliveredAt'] = $results[0]->delivered_at;
            $sprint['finishedAt']  = TZ::getLocalDatetime2($timezone, $results[0]->finished_at, 'Y-m-d H:i:s');

            // 2. Récupération des tâches
            // --------------------------
            $query      = '
                SELECT
                    task.id                     AS taskId,
                    task.name                   AS taskName,
                    task.description            AS taskDescription,
                    task.initial_duration       AS taskInitialDuration,
                    task.remaining_duration     AS taskRemainingDuration,
                    task.user_id                AS taskUserId,
                    task.added_after            AS taskAddedAfter,
                    task_user.id                AS taskPartId,
                    task_user.user_id           AS taskPartUserId,
                    task_user.duration          AS taskPartDuration,
                    task_user.worked_duration   AS taskPartWorkedDuration,
                    task_user.date              AS taskPartDate
                FROM task
                    LEFT JOIN task_user ON task_user.task_id = task.id
                WHERE task.sprint_id = :sprintId
                ORDER BY task.created_at ASC';
            $results    = DB::select($query, ['sprintId' => $sprintId]);
            $tasks      = [];
            $tasksUsers = [];
            foreach ($results as $task)
            {
                $taskId     = $task->taskId;
                $taskPartId = $task->taskPartId;

                if (!array_key_exists($taskId, $tasks))
                {
                    $tasks[$taskId]['id']                = $taskId;
                    $tasks[$taskId]['name']              = $task->taskName;
                    $tasks[$taskId]['description']       = $task->taskDescription;
                    $tasks[$taskId]['initialDuration']   = floatval($task->taskInitialDuration);
                    $tasks[$taskId]['remainingDuration'] = floatval($task->taskRemainingDuration);
                    $tasks[$taskId]['userId']            = $task->taskUserId;
                    $tasks[$taskId]['addedAfter']        = $task->taskAddedAfter;
                }

                if ($taskPartId && !array_key_exists($taskPartId, $tasksUsers))
                {
                    $tasksUsers[$taskPartId]['id']             = $taskPartId;
                    $tasksUsers[$taskPartId]['taskId']         = $task->taskId;
                    $tasksUsers[$taskPartId]['userId']         = $task->taskPartUserId;
                    $tasksUsers[$taskPartId]['duration']       = floatval($task->taskPartDuration);
                    $tasksUsers[$taskPartId]['workedDuration'] = floatval($task->taskPartWorkedDuration);
                    $tasksUsers[$taskPartId]['date']           = $task->taskPartDate; // TZ::getLocalDatetime2($timezone, $task->taskPartDate, 'Y-m-d');
                }
            }

            // 3. Récupération des utilisateurs
            // --------------------------------
            $users   = [];
            $query   = '
                SELECT DISTINCT 
                    users.id,
                    users.firstname,
                    users.lastname, 
                    users.email,
                    users.worked_hours_per_day,
                    users.group_id
                FROM users
                    LEFT JOIN task_user ON task_user.user_id = users.id
                    LEFT JOIN task ON task_user.task_id = task.id
                    LEFT JOIN team_member ON team_member.user_id = users.id
                WHERE team_member.team_id = :teamId OR task.sprint_id = :sprintId
                ORDER BY users.firstname ASC, users.lastname ASC';
            $results = DB::select($query, ['teamId' => $sprint['teamId'], 'sprintId' => $sprintId]);
            if ($results && count($results) > 0)
            {
                foreach ($results as $user)
                {
                    if (!array_key_exists($user->id, $users))
                    {
                        $users[$user->id]['id']                = intval($user->id);
                        $users[$user->id]['firstname']         = $user->firstname;
                        $users[$user->id]['lastname']          = $user->lastname;
                        $users[$user->id]['email']             = $user->email;
                        $users[$user->id]['groupId']           = intval($user->group_id);
                        $users[$user->id]['workedHoursPerDay'] = intval($user->worked_hours_per_day);
                    }
                }
            }

            $sprint['users']      = array_values($users);
            $sprint['tasks']      = $tasks;
            $sprint['tasksUsers'] = array_values($tasksUsers);

            return [
                'code'    => 200,
                'message' => 'Success',
                'data'    => $sprint,
            ];
        } 
        
        /**
         * Récupération d'une taskUser
         * 
         * @author Fabien Bellanger
         * @param int    $userId   ID de l'utilisateur
         * @param int    $taskId   ID de la tâche
         * @param string $date     Date
         * @return null|array
         */
        static private function getTaskUser(int $taskId, int $userId, string $date): ?array
        {
            $query      = '
                SELECT id, date, duration, worked_duration
                FROM task_user 
                WHERE task_id = :taskId AND user_id = :userId AND date LIKE :date';
            $results    = DB::select($query, [
                'taskId' => $taskId,
                'userId' => $userId,
                'date'   => $date,
            ]);
            $taskUser = null;
            if ($results && count($results) === 1)
            {
                $taskUser['id']             = $results[0]->id;
                $taskUser['date']           = $results[0]->date;
                $taskUser['duration']       = $results[0]->duration;
                $taskUser['workedDuration'] = $results[0]->worked_duration;
            }

            return $taskUser;
        } 

        /**
         * Création / Modification d'une taskUser
         *
         * @author Fabien Bellanger
         * @param int   $userId   ID de l'utilisateur
         * @param int   $sprintId ID du sprint
         * @param int   $taskId   ID de la tâche
         * @param array $data     Données
         * @return array
         */
        static public function editTaskUser(int $userId, int $sprintId, int $taskId, array $data): array
        {
            // 1. Sprint valide ?
            // ------------------
            if (!self::isSprintValid($userId, $sprintId))
            {
                return [
                    'code'    => 404,
                    'message' => 'No sprint found',
                ];
            }

            // 2. Recherche de la taskUser
            // ---------------------------
            $taskUser   = self::getTaskUser($taskId, $data['userId'], $data['date']);
            $taskUserId = ($taskUser) ? $taskUser['id'] : 0;

            // Timezone
            $timezone  = UserRepository::getTimezone();
            $createdAt = TZ::getUTCDatetime2($timezone, date('Y-m-d H:i:s'), 'Y-m-d H:i:s');
            
            if (!$taskUserId)
            {
                // Création
                // --------
                $taskUserData = [
                    'task_id'         => $taskId,
                    'user_id'         => $data['userId'],
                    'date'            => $data['date'], // TZ::getUTCDatetime2($timezone, $data['date'], 'Y-m-d'),
                    'duration'        => $data['duration'],
                    'worked_duration' => $data['workedDuration'],
                    'created_at'      => $createdAt,
                    'updated_at'      => $createdAt,
                ];
                $taskUserId   = DB::table('task_user')->insertGetId($taskUserData);
            }
            else
            {
                // Modification
                // ------------
                $taskUserData = [
                    'duration'        => $data['duration'],
                    'worked_duration' => $data['workedDuration'],
                    'updated_at'      => $createdAt,
                ];
                DB::table('task_user')
                  ->where('id', $taskUserId)
                  ->update($taskUserData);
            }

            // 3. Modification de la tâche
            // ---------------------------
            $taskData = ['remaining_duration' => $data['remainingDuration']];
            if ($data['remainingDuration'] == 0)
            {
                $taskData['finished_at'] = TZ::getUTCDatetime2($timezone, date('Y-m-d H:i:s'), 'Y-m-d H:i:s');
            }

            DB::table('task')
              ->where('id', $taskId)
              ->update($taskData);

            return [
                'code'    => 200,
                'message' => 'Success',
                'data'    => ['taskUserId' => $taskUserId],
            ];
        }

        /**
         * Ajout d'un sprint
         *
         * @author Fabien Bellanger
         * @param int   $userId   ID de l'utilisateur
         * @param array $data     Données
         * @return array
         */
        static public function newSprint(int $userId, array $data): array
        {
            // 1. Verification de la validité de l'équipe
            // ------------------------------------------
            // TODO

            // 2. Ajout en base de données
            // ---------------------------
            $timezone  = UserRepository::getTimezone();
            $createdAt = TZ::getUTCDatetime2($timezone, date('Y-m-d H:i:s'), 'Y-m-d H:i:s');
            $sprintData = [
                'name'         => $data['name'],
                'team_id'      => $data['teamId'],
                'created_at'   => $createdAt,
                'updated_at'   => $createdAt,
                'started_at'   => $data['startedAt'],
                'delivered_at' => $data['deliveredAt'],
                'finished_at'  => null,
            ];
            $sprintId = DB::table('sprint')->insertGetId($sprintData);

            return [
                'code'    => 200,
                'message' => 'Success',
                'data'    => ['sprintId' => $sprintId],
            ];
        }

        /**
         * Suppression d'un sprint
         *
         * @author Fabien Bellanger
         * @param int   $userId   ID de l'utilisateur
         * @param int   $sprintId ID du sprint
         * @return array
         */
        static public function deleteSprint($userId, $sprintId): array
        {
            // 1. Sprint valide ?
            // ------------------
            if (!self::isSprintValid($userId, $sprintId))
            {
                return [
                    'code'    => 404,
                    'message' => 'No sprint found',
                ];
            }

            // 2. Suppression du sprint
            // ------------------------
            DB::table('sprint')
                ->where('id', $sprintId)
                ->delete();

            return [
                'code'    => 200,
                'message' => 'Sprint successful deleted',
            ];
        }

        /**
         * Suppression d'un taskUser
         *
         * @author Fabien Bellanger
         * @param int    $userId   ID de l'utilisateur
         * @param int    $sprintId ID du sprint
         * @param int    $taskId   ID de la tâche
         * @param string $date     Date
         * @return array
         */
        public static function deleteTaskUser(int $userId, int $sprintId, int $taskId, string $date): array
        {
            // 1. Sprint valide ?
            // ------------------
            if (!self::isSprintValid($userId, $sprintId))
            {
                return [
                    'code'    => 404,
                    'message' => 'No sprint found',
                ];
            }

            // 2. Recherche de la taskUser
            // ---------------------------
            $taskUser   = self::getTaskUser($taskId, $userId, $date);
            $taskUserId = ($taskUser) ? $taskUser['id'] : 0;
            if (!$taskUserId)
            {
                return [
                    'code'    => 404,
                    'message' => 'No taskUser found',
                ];
            }

            // 3. On rajoute de temps à la tâche
            // ---------------------------------
            $taskRemainingDuration = TaskRepository::getTaskRemainingDuration($taskId);
            $taskData              = ['remaining_duration' => $taskRemainingDuration + $taskUser['duration']];
            DB::table('task')
              ->where('id', $taskId)
              ->update($taskData);

            // 4. Suppression de la taskUser
            // -----------------------------
            DB::table('task_user')
                ->where('id', $taskUserId)
                ->delete();

            return [
                'code'    => 200,
                'message' => 'TaskUser successful deleted',
            ];
        }

        /**
         * Terminaison / Ré-ouverture d'un sprint
         *
         * @author Fabien Bellanger
         * @param int    $userId   ID de l'utilisateur
         * @param int    $sprintId ID du sprint
         * @param string $state    Etat {'finish', 're-open'}
         * @return array
         */
        public static function finishOrReOpenSprint($userId, $sprintId, $state): array
        {
            // 1. Sprint valide ?
            // ------------------
            if (!self::isSprintValid($userId, $sprintId))
            {
                return [
                    'code'    => 404,
                    'message' => 'No sprint found',
                ];
            }

            // 2. On met à jour finished_at
            // ----------------------------
            $finishedAt = null;
            if ($state == 'finish')
            {
                $timezone   = UserRepository::getTimezone();
                $finishedAt = TZ::getUTCDatetime2($timezone, date('Y-m-d H:i:s'), 'Y-m-d H:i:s');
            }
            DB::table('sprint')
              ->where('id', $sprintId)
              ->update(['finished_at' => $finishedAt]);

            return [
                'code'    => 200,
                'message' => 'Sprint successful updated',
            ];
        }
    }
    