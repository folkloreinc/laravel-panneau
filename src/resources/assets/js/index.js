/* globals panneau_config */
import domready from 'domready';
import panneau from 'panneau';

import '../styles/main.global.scss';

domready(() => {
    const el = document.getElementById('panneau');
    if (el === null) {
        return;
    }
    const { definition, user, locale, ...opts } = panneau_config();
    panneau()
        .setDefinition(definition)
        .setUser(user)
        .setLocale(locale)
        .render(el);
});
