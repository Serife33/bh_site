import './stimulus_bootstrap.js';
/*
 * Welcome to your app's main JavaScript file!
 *
 * This file will be included onto the page via the importmap() Twig function,
 * which should already be in your base.html.twig.
 */
import './styles/app.css';

console.log('This log comes from assets/app.js - welcome to AssetMapper! 🎉');

// ===== Menu burger mobile (délégation d'événement) =====
document.addEventListener('click', (e) => {
    // clic sur le burger (ou un de ses <span>)
    if (e.target.closest('.burger')) {
        const nav = document.querySelector('.nav');
        const burger = document.querySelector('.burger');
        const isOpen = nav.classList.toggle('open');
        burger.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
        return;
    }
    // clic sur un lien du menu → on ferme
    if (e.target.closest('.nav a')) {
        document.querySelector('.nav')?.classList.remove('open');
    }
});
