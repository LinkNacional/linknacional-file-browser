const path = require('path');
const CopyPlugin = require('copy-webpack-plugin');

module.exports = {
    mode: 'production',
    entry: {
        'fontawesome.compiled': './assets/js/fontawesome-entry.js',
        'pdfjs.compiled': './assets/js/pdfjs-entry.js',
    },
    output: {
        path: path.resolve(__dirname, 'assets/js/compiled'),
        filename: '[name].js',
        clean: true,
    },
    module: {
        rules: [
            {
                test: /\.css$/i,
                use: ['style-loader', 'css-loader'],
            },
            {
                test: /\.(woff|woff2|eot|ttf|otf)$/i,
                type: 'asset/inline',
            },
        ],
    },
    plugins: [
        new CopyPlugin({
            patterns: [
                { from: 'node_modules/pdfjs-dist/build/pdf.worker.min.js', to: 'pdf.worker.min.js' },
            ],
        }),
    ],
};
