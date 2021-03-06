import { Component, OnInit } from '@angular/core';
import { Router } from '@angular/router';
import { MatDialog, MatSnackBar } from '@angular/material';

import { TranslateService } from '@ngx-translate/core';

import { SprintDeleteDialogComponent } from './dialogs/sprint-delete-dialog.component';
import { ApiSprintService } from '../../api';
import { SprintService } from '../services/sprint.service';

@Component({
    selector:    'sa-sprint-list',
    templateUrl: './sprint-list.component.html',
})

export class SprintListComponent implements OnInit
{
    public sprints: any[]   = [];
    public loading: boolean = true;
    public state: string;

    /**
     * Constructeur
     *
     * @author Fabien Bellanger
     * @param {ApiSprintService} apiSprintService
     * @param {TranslateService} translateService
     * @param {Router}           router
     * @param {MatDialog}        dialog
     * @param {MatSnackBar}      snackBar
     * @param {SprintService}    sprintService
     */
    constructor(private apiSprintService: ApiSprintService,
                private translateService: TranslateService,
                private router: Router,
                private dialog: MatDialog,
                private snackBar: MatSnackBar,
                private sprintService: SprintService)
    {
    }

    /**
     * Initialisation du composant
     *
     * @author Fabien Bellanger
     */
    public ngOnInit(): void
    {
        this.getSprints(this.state);
    }

    /**
     * Liste des sprints
     *
     * @author Fabien Bellanger
     * @param {string} state Etat {all, inProgress, finished}
     */
    public getSprints(state: string): void
    {
        if (state !== 'all' && state !== 'inProgress' && state !== 'finished')
        {
            state = 'inProgress';
        }
        this.state   = state;
        this.loading = true;

        this.apiSprintService.getList(state)
            .then((sprints: any) =>
            {
                // Date de fin théorique des sprints et performance
                // ------------------------------------------------
                for (const sprintIndex in sprints)
                {
                    if (sprints.hasOwnProperty(sprintIndex))
                    {
                        sprints[sprintIndex]['estimatedFinishedAt'] = this.sprintService.getSprintEndDate(sprints[sprintIndex]);
                        sprints[sprintIndex]['performance']         = (sprints[sprintIndex]['workedDuration'] !== 0)
                            ? (sprints[sprintIndex]['decrementedDuration'] / sprints[sprintIndex]['workedDuration']) * 100
                            : 0;
                        sprints[sprintIndex]['tasksAddedPercent']   = (sprints[sprintIndex]['workedDuration'] !== 0)
                            ? (sprints[sprintIndex]['addedWorkedDuration'] / sprints[sprintIndex]['workedDuration']) * 100
                            : 0;
                    }
                }
                this.sprints = sprints;

                this.loading = false;
            })
            .catch(() =>
            {
                console.error('[error] Get sprints list');

                this.loading = false;
            });
    }

    /**
     * Redirection vers la gestion des tâches
     *
     * @author Fabien Bellanger
     * @param {any} sprint Sprint
     */
    public redirectToTasksManagement(sprint: any): void
    {
        if (sprint.initialDuration !== null && sprint.finishedAt === null)
        {
            this.router.navigate(['/sprints', sprint.id, 'tasks-management-list']);
        }
    }

    /**
     * Terminaison / Ré-ouverture d'un sprint
     *
     * @author Fabien Bellanger
     * @param {any}    sprint Sprint
     * @param {string} state  Etat {'finish', 're-open'}
     */
    public finishReOpenSprint(sprint: any, state: string): void
    {
        if (state === 'finish' || state === 're-open')
        {
            this.apiSprintService.finishOrReOpenSprint(sprint.id, state)
                .then(() =>
                {
                    // Rechargement des données
                    // ------------------------
                    this.getSprints(this.state);
                    
                    // Notification
                    // ------------
                    this.translateService.get(['update.sprint.success', 'success'])
                        .subscribe((translationObject: Object) =>
                        {
                            this.snackBar.open(
                                translationObject['update.sprint.success'],
                                translationObject['success'],
                                {
                                    duration: 3000,
                                });
                        });
                })
                .catch(() =>
                {
                    // Notification
                    // ------------
                    this.translateService.get(['update.sprint.error', 'error'])
                        .subscribe((translationObject: Object) =>
                        {
                            this.snackBar.open(
                                translationObject['update.sprint.error'],
                                translationObject['error'],
                                {
                                    duration: 3000,
                                });
                        });
                });
        }
    }

    /**
     * Suppression d'un sprint
     *
     * @author Fabien Bellanger
     * @param {any} sprint Sprint
     */
    public deleteSprint(sprint: any): void
    {
        const dialog = this.dialog.open(SprintDeleteDialogComponent, {
            data: {
                confirm: true,
            },
            disableClose: false,
        });

        dialog.afterClosed().subscribe((result: any) =>
        {
            this.translateService.get([
                'delete.sprint.success',
                'delete.sprint.error',
                'error',
                'success',
            ]).subscribe((translationObject: Object) =>
            {
                if (result === true)
                {
                    this.apiSprintService.deleteSprint(sprint.id)
                        .then(() =>
                        {
                            // Rechargement des données
                            // ------------------------
                            this.getSprints(this.state);

                            // Notification
                            // ------------
                            this.snackBar.open(
                                translationObject['delete.sprint.success'],
                                translationObject['success'],
                                {
                                    duration: 3000,
                                });
                        })
                        .catch(() =>
                        {
                            // Notification
                            // ------------
                            this.snackBar.open(
                                translationObject['delete.sprint.error'],
                                translationObject['error'],
                                {
                                    duration: 3000,
                                });
                        });
                }
            });
        });
    }
}
