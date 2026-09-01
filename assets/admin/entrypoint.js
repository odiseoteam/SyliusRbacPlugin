import { Application } from '@hotwired/stimulus';

import './styles/rbac-permissions.css';

import RbacPermissionsController from './controllers/rbac_permissions_controller';

/**
 * The plugin's assets are a webpack entry of their own, so the Stimulus application the admin
 * starts is not reachable from here. Starting a second one is supported: Stimulus scopes
 * controllers by identifier, and the plugin's own is namespaced.
 */
const application = Application.start();

application.register('rbac-permissions', RbacPermissionsController);
