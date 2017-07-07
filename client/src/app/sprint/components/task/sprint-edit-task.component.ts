import { Component, OnInit } from '@angular/core';
import { Router, ActivatedRoute } from '@angular/router';

import { ToastyService } from 'ng2-toasty';
import { TranslateService } from '@ngx-translate/core';

import { ApiSprintService } from '../../../api';
import { StorageService } from '../../../shared';
import { SprintService } from '../../services/sprint.service';

import { Task } from '../../../models';

@Component({
    selector:    'sa-sprint-edit-task-info',
    templateUrl: './sprint-edit-task.component.html',
})

export class SprintEditTaskComponent implements OnInit
{
	private sprintId: number;
    private id: number;
	private name: string;
	private description: string;
    private duration: number;
    private remainingDuration: number;
    private applications: any[];
    private applicationsIds: any;
    private notPlanned: boolean;
    private title: string;
    private buttonTitle: string;
    private task: Task;
    private loading: boolean = true;

    /**
     * Constructeur
     *
     * @author Fabien Bellanger
     * @param {ApiSprintService}    apiSprintService
     * @param {ActivatedRoute}      route
     * @param {SprintService}       sprintService
     * @param {StorageService}      storageService
     * @param {ToastyService}       toastyService
     * @param {Router}              router
     * @param {TranslateService}    translateService
     */
    constructor(private apiSprintService: ApiSprintService,
                private route: ActivatedRoute,
                private sprintService: SprintService,
                private storageService: StorageService,
                private toastyService: ToastyService,
                private router: Router,
				private translateService: TranslateService)
    {
    }

    /**
     * Initialisation du composant
     *
     * @author Fabien Bellanger
     */
    public ngOnInit(): void
    {
        // Récupération de l'ID du sprint et de la tâche
        // ---------------------------------------------
        this.sprintId = +this.route.snapshot.params['sprintId'];
        this.id       = +this.route.snapshot.params['taskId'];
        this.id       = (isNaN(this.id)) ? 0 : this.id;

        // Titre
        // -----
        this.translateService.get([
            'add.task.title', 
            'edit.task.title',
            'add',
            'modify',
        ]).subscribe((transltationObject: Object) =>
		{
            this.title = (this.id === 0)
                ? transltationObject['add.task.title']
                : transltationObject['edit.task.title'];

            this.buttonTitle = (this.id === 0)
                ? transltationObject['add']
                : transltationObject['modify'];
        });

		// Initialisation
		// --------------
        this.applications = this.storageService.get('session', 'applications', []);

        if (this.id === 0)
        {
            this.name            = '';
            this.description     = '';
            this.duration        = null;
            this.notPlanned      = false;
            this.applicationsIds = {};

            this.loading = false;
        }
        else
        {
            this.apiSprintService.getTask(this.sprintId, this.id)
                .then((response: any) =>
                {  
                    this.name            = response.name;
                    this.description     = response.description;
                    this.duration        = response.initialDuration;
                    this.notPlanned      = response.addedAfter;

                    // Construction du tableau des applications
                    // ----------------------------------------
                    const applicationIds = response.applications;
                    this.applicationsIds = {};
                    for (let applicationIndex in this.applications)
                    {
                        if (applicationIds.indexOf(this.applications[applicationIndex].id) !== -1)
                        {
                            this.applicationsIds[applicationIndex] = this.applications[applicationIndex].id;
                        }
                    }

                    this.loading = false;
                })
                .catch(() =>
                {
                    this.loading = false;

                    // Notification
                    // ------------
                    this.translateService.get('get.task.error').subscribe((msg: string) =>
                    {
                        this.toastyService.error(msg);
                    });
                });
        }
    }

	/**
	 * Ajout / Modification d'une tâche
	 * 
	 * @author Fabien Bellanger
	 */
	private editTask(): void
	{
        // Conversion Object => Array
        // --------------------------
        let applicationsIdsSelected: number[] = Object.keys(this.applicationsIds)
                                                      .map((k: any) => this.applicationsIds[k]);

        // Requête
        // -------
        if (this.id === 0)
        {
            // Création
            // --------
            this.addTask(applicationsIdsSelected);
        }
        else
        {
            // Modification
            // ------------
            this.modifyTask(applicationsIdsSelected);
        }
	}

    /**
	 * Ajout d'une tâche
	 * 
	 * @author Fabien Bellanger
     * @param {number[]} applicationsIdsSelected Tableau d'ID des applications sélectionnées
	 */
    private addTask(applicationsIdsSelected: number[]): void
    {
        this.apiSprintService.addTask(this.sprintId, {
            name:            this.name,
            description:     this.description,
            duration:        this.duration,
            notPlanned:      +this.notPlanned,
            applicationsIds: applicationsIdsSelected,
        }).then((task: any) =>
        {
            // Notification
            // ------------
            this.translateService.get('add.task.success').subscribe((msg: string) =>
            {
                this.toastyService.success(msg);
            });

            // Redirection
            // -----------
            this.router.navigate(['/sprints/tasks', {sprintId: this.sprintId}]);
        })
        .catch((error: any) =>
        {
            // Notification
            // ------------
            this.translateService.get('add.task.error').subscribe((msg: string) =>
            {
                this.toastyService.error(msg);
            });
        });
    }

    /**
	 * Modification d'une tâche
	 * 
	 * @author Fabien Bellanger
     * @param {number[]} applicationsIdsSelected Tableau d'ID des applications sélectionnées
	 */
    private modifyTask(applicationsIdsSelected: number[]): void
    {
        this.apiSprintService.modifyTask(this.sprintId, this.id, {
            name:            this.name,
            description:     this.description,
            duration:        this.duration,
            notPlanned:      +this.notPlanned,
            applicationsIds: applicationsIdsSelected,
        }).then((task: any) =>
        {
            // Notification
            // ------------
            this.translateService.get('modify.task.success').subscribe((msg: string) =>
            {
                this.toastyService.success(msg);
            });

            // Redirection
            // -----------
            this.router.navigate(['/sprints/tasks', {sprintId: this.sprintId}]);
        })
        .catch((error: any) =>
        {
            // Notification
            // ------------
            this.translateService.get('modify.task.error').subscribe((msg: string) =>
            {
                this.toastyService.error(msg);
            });
        });
    }
    
    /**
     * Sélection ou désélection d'une application
     *
     * @author Fabien Bellanger
     * @param {any)     event
     * @param {number}  index
     * @param {number}  applicationId
     */
    private toggleApplication(event: any, index: number, applicationId: number): void
    {
        const isApplicationPresent: boolean = (this.applicationsIds[index] !== undefined);

        if (event.target.checked && !isApplicationPresent)
        {
            this.applicationsIds[index] = applicationId;
        }
        else if (!event.target.checked && isApplicationPresent)
        {
            delete this.applicationsIds[index];
        }
    }
}