/* globals panneau, panneau_config */
import domready from 'domready';
import FieldsGroup from '@panneau/fields-group';

domready(() => {
    const { definition, ...opts } = panneau_config();
    panneau()
        .setDefinition(definition)
        .setOptions({
            locale: 'fr',
            ...opts,
        })
        .components('fields.group', FieldsGroup)
        .render(document.getElementById('panneau'));
});
