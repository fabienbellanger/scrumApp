import { Injectable } from '@angular/core';

import { StorageService } from '../../shared';
import { User } from '../../models';

import { Md5 } from 'ts-md5/dist/md5';

@Injectable()

export class UserService
{
	public user: User;

    /**
     * Constructeur
     *
     * @author Fabien Bellanger
	 * @param {StorageService} storageService
     */
    constructor(private storageService: StorageService)
    {
		this.user = this.storageService.get('session', 'user', null);
    }

	/**
	 * Initialisation
	 * 
	 * @author Fabien Bellanger
	 * @param {User} user Utilisateur
	 */
	public init(user: User): void
	{
		this.user = user;

		// Enregistrement en session
		// -------------------------
		this.storageService.set('session', 'user', user);
	}

    /**
     * Retourne le nom complet
     * 
     * @author Fabien Bellanger
     * @return {string} Nom complet
     */
    public getFullname(): string
    {
        return (this.user !== null) ? this.user.firstname + ' ' + this.user.lastname : '';
    }

    /**
     * Retourne l'URL Gravatar'
     * 
     * @author Fabien Bellanger
     * @param {number} size Taille en px
     * @return {string} URL Gravatar
     */
    public getGravatarUrl(size: number = 20): string | Int32Array
    {
        let url: string = '';

        if (this.user !== null && this.user.email !== null && this.user.email !== '' && this.user.email !== undefined)
        {
            let md5: Md5 = new Md5();

            url = 'https://www.gravatar.com/avatar/' + Md5.hashStr(this.user.email) + '?s=' + size;
        }

        return url;
    }
}
