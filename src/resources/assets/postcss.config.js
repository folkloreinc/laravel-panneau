module.exports = ({ options }) => ({
    map: {
        inline: false,
    },
    plugins: {
        autoprefixer: {},
        cssnano: {
            preset: 'default',
            zindex: false,
            discardUnused: {
                fontFace: false,
            },
        },
    },
});
