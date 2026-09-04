/* Browser entry point. Each feature owns its own small module. */
import { initializePreferences } from './preferences.js';
import { initializePostForms } from './post-forms.js';
import { initializePostMenus } from './post-menus.js';
import { initializeModeratorForms } from './moderator.js';
import { initializeNavigation } from './navigation.js';

initializePreferences();
initializePostForms();
initializePostMenus();
initializeModeratorForms();
initializeNavigation();
