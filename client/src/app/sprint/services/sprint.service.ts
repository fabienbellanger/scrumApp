import { Injectable } from '@angular/core';

import { StorageService } from '../../shared';
import { Sprint } from '../../models';

@Injectable()

export class SprintService
{
    public sprint: Sprint;
    public initialDuration: number;
    public addedDuration: number;
    public totalDuration: number;
    public remainingDuration: number;

    /**
     * Constructeur
     *
     * @author Fabien Bellanger
     */
    constructor(private storageService: StorageService)
    {
        this.sprint            = null;
        this.initialDuration   = 0;
        this.addedDuration     = 0;
        this.totalDuration     = 0;
        this.remainingDuration = 0;
    }

    /**
     * Initialisation
     *
     * @author Fabien Bellanger
     * @param {Sprint} sprint Sprint
     */
    public init(sprint: Sprint): void
    {
        this.sprint            = sprint;
        this.initialDuration   = this.getDuration(false);
        this.addedDuration     = this.getDuration(true);
        this.totalDuration     = this.initialDuration + this.addedDuration;
        this.remainingDuration = this.getRemainigDuration();
    }

    /**
     * Retourne une durée
     *
     * @author Fabien Bellanger
     * @param {boolean} added Tâches non prévues
     * @return {number} Durée
     */
    private getDuration(added: boolean): number
    {
        let duration: number = 0;

        if (this.sprint !== null)
        {
            for (let task of this.sprint.tasks)
            {
                if (task.addedAfter == added)
                {
                    duration += +task.initialDuration;
                }
            }
        }

        return duration;
    }

    /**
     * Retourne la durée restante
     *
     * @author Fabien Bellanger
     * @return {number} Durée
     */
    private getRemainigDuration(): number
    {
        let duration: number = 0;

        if (this.sprint !== null)
        {
            for (let task of this.sprint.tasks)
            {
                duration += +task.remainingDuration;
            }
        }

        return duration;
    }
}