/* globals panneau, panneau_config */
import domready from 'domready';

import '../styles/main.global.scss';

domready(() => {
    const { definition, ...opts } = panneau_config();
    panneau()
        .setDefinition(definition)
        .setOptions({
            locale: 'fr',
            ...opts,
        })
        .render(document.getElementById('panneau'));
});
