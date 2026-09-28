/**
 * Expose Mozilla pdf.js on `window.pdfjsLib` for the plugin viewer.
 *
 * The worker script is emitted next to this bundle by copy-webpack-plugin; the
 * plugin localizes its URL into `linknacional_*_ajax.pdfjs_worker`, which the
 * viewer assigns to `pdfjsLib.GlobalWorkerOptions.workerSrc` before use.
 */
import * as pdfjsLib from 'pdfjs-dist';

window.pdfjsLib = pdfjsLib;
