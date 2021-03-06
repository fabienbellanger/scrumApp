import { Injectable } from '@angular/core';
import { Headers } from '@angular/http';

import { HttpService, StorageService } from '../../shared';

@Injectable()

export class ApiTeamService
{
    /**
     * Constructeur
     *
     * @author Fabien Bellanger
     * @param {HttpService}       httpService
     * @param {StorageService}    storageService
     */
    constructor(private httpService: HttpService,
                private storageService: StorageService)
    {
    }
    
    /**
     * Récupération des équipes
     *
     * @author Fabien Bellanger
     * @return {Promise}
     */
    public getTeams(): any
    {
        return new Promise((resolve: any, reject: any) =>
        {
            // 1. Récupération du token
            // ------------------------
            const token: string = this.storageService.get('session', 'token', null);

            if (token != null)
            {
                // 2. Requête
                // ----------
                const headers: any = new Headers();
                headers.append('Authorization', 'Bearer ' + token);
                headers.append('Content-Type', 'application/json');
                
                this.httpService.get('/teams', {headers: headers}, true, true)
                    .then((data: any) =>
                    {
                        resolve(data);
                    })
                    .catch(() =>
                    {
                        reject();
                    });
            }
            else
            {
                reject();
            }
        });
    }
    
    /**
     * Récupération des informations pour créer ou modifier une équipe
     *
     * @author Fabien Bellanger
     * @param {number} teamId ID de l'équipe
     * @return {Promise}
     */
    public getEditInfo(teamId: number = 0): any
    {
        return new Promise((resolve: any, reject: any) =>
        {
            // 1. Récupération du token
            // ------------------------
            const token: string = this.storageService.get('session', 'token', null);

            if (token != null)
            {
                // 2. Requête
                // ----------
                const headers: any = new Headers();
                headers.append('Authorization', 'Bearer ' + token);
                headers.append('Content-Type', 'application/json');
                
                this.httpService.get('/teams/edit/' + teamId, {headers: headers}, true, true)
                    .then((data: any) =>
                    {
                        resolve(data);
                    })
                    .catch(() =>
                    {
                        reject();
                    });
            }
            else
            {
                reject();
            }
        });
    }
    
    /**
     * Création d'une équipe
     *
     * @author Fabien Bellanger
     * @param {any[]}  data     Données
     * @return {Promise}
     */
    public addTeam(data: any): Promise<any>
    {
        return new Promise((resolve: any, reject: any) =>
        {
            // 1. Récupération du token
            // ------------------------
            const token: string = this.storageService.get('session', 'token', null);

            if (token != null)
            {
                // 2. Requête
                // ----------
                const headers: any = new Headers();
                headers.append('Authorization', 'Bearer ' + token);
                headers.append('Content-Type', 'application/x-www-form-urlencoded');
                
                this.httpService.post('/teams', {data: JSON.stringify(data)}, {headers: headers}, true, true)
                    .then((response: any) =>
                    {
                        resolve(response);
                    })
                    .catch(() =>
                    {
                        reject();
                    });
            }
            else
            {
                reject();
            }
        });
    }
    
    /**
     * Modification d'une équipe
     *
     * @author Fabien Bellanger
     * @param {number} teamId   ID de l'équipe
     * @param {any[]}  data     Données
     * @return {Promise}
     */
    public modifyTeam(teamId: number, data: any): Promise<any>
    {
        return new Promise((resolve: any, reject: any) =>
        {
            // 1. Récupération du token
            // ------------------------
            const token: string = this.storageService.get('session', 'token', null);

            if (token != null)
            {
                // 2. Requête
                // ----------
                const headers: any = new Headers();
                headers.append('Authorization', 'Bearer ' + token);
                headers.append('Content-Type', 'application/x-www-form-urlencoded');
                
                this.httpService.put('/teams/' + teamId, {data: JSON.stringify(data)}, {headers: headers}, true, true)
                    .then((response: any) =>
                    {
                        resolve(response);
                    })
                    .catch(() =>
                    {
                        reject();
                    });
            }
            else
            {
                reject();
            }
        });
    }
    
    /**
     * Récupération des équipes
     *
     * @author Fabien Bellanger
     * @param {number} teamId ID de l'équipe
     * @return {Promise}
     */
    public deleteTeam(teamId: number): any
    {
        return new Promise((resolve: any, reject: any) =>
        {
            // 1. Récupération du token
            // ------------------------
            const token: string = this.storageService.get('session', 'token', null);

            if (token != null && teamId > 0)
            {
                // 2. Requête
                // ----------
                const headers: any = new Headers();
                headers.append('Authorization', 'Bearer ' + token);
                headers.append('Content-Type', 'application/json');
                
                this.httpService.delete('/teams/' + teamId, {headers: headers}, true, true)
                    .then((data: any) =>
                    {
                        resolve(data);
                    })
                    .catch(() =>
                    {
                        reject();
                    });
            }
            else
            {
                reject();
            }
        });
    }
    
    /**
     * Récupération de toutes les équipes
     *
     * @author Fabien Bellanger
     * @return {Promise}
     */
    public getAllTeams(): any
    {
        return new Promise((resolve: any, reject: any) =>
        {
            // 1. Récupération du token
            // ------------------------
            const token: string = this.storageService.get('session', 'token', null);

            if (token != null)
            {
                // 2. Requête
                // ----------
                const headers: any = new Headers();
                headers.append('Authorization', 'Bearer ' + token);
                headers.append('Content-Type', 'application/json');
                
                this.httpService.get('/teams/all', {headers: headers}, true, true)
                    .then((data: any) =>
                    {
                        resolve(data);
                    })
                    .catch(() =>
                    {
                        reject();
                    });
            }
            else
            {
                reject();
            }
        });
    }
}
