import 'htmx.org';
import Alpine from 'alpinejs'
import morph from "@alpinejs/morph";
import Toastify from 'toastify-js'
import * as formeo from '@jonas-flexxter/formeo';
import sort from "@alpinejs/sort";
import persist from "@alpinejs/persist";
import formBuilderMixin from './FormBuilderMixin.js';

Alpine.plugin(morph)
Alpine.plugin(sort)
Alpine.plugin(persist)

Alpine.store('global', {})

window.Alpine = Alpine
window.formeo = formeo;
window.formBuilderMixin = formBuilderMixin;

// Flash messages - mostra notificações ao usuário
window.flash = function(text, type = 'success') {
    document.body.dispatchEvent(new CustomEvent('toast', {
        detail: {
            value: JSON.stringify({
                text: text,
                type: type
            })
        }
    }));
};

// Flash e recarrega a página (mantém a mensagem após reload)
window.flashAndReload = function(text, type = 'success') {
    sessionStorage.setItem('pendingFlash', JSON.stringify({ text, type }));
    window.location.reload();
};

// Flash e redireciona para outra URL (mantém a mensagem após redirect)
window.flashAndRedirect = function(url, text, type = 'success') {
    sessionStorage.setItem('pendingFlash', JSON.stringify({ text, type }));
    window.location.href = url;
};

// Aliases para compatibilidade com código existente
window.showToast = window.flash;
window.showToastAndReload = window.flashAndReload;
window.showToastAndRedirect = window.flashAndRedirect;

window.showConfirmModal = function(options) {
    const {
        title = 'Confirmar Ação',
        message = 'Tem certeza que deseja continuar?',
        confirmText = 'Confirmar',
        cancelText = 'Cancelar',
        type = 'warning',
        onConfirm = () => {},
        onCancel = () => {}
    } = options;

    const existingModal = document.getElementById('globalConfirmModal');
    if (existingModal) {
        existingModal.remove();
    }

    const icons = {
        warning: 'warning',
        error: 'error',
        info: 'info',
        danger: 'dangerous'
    };

    const colors = {
        warning: 'text-warning',
        error: 'text-error',
        info: 'text-info',
        danger: 'text-error'
    };

    const modalHTML = `
        <dialog id="globalConfirmModal" class="modal">
            <div class="modal-box">
                <h3 class="font-bold text-lg flex items-center gap-2">
                    <span translate="no" class="material-symbols-outlined ${ colors[type] }">${ icons[type] }</span>
                    ${ title }
                </h3>
                
                <div class="py-4">
                    <p class="text-base whitespace-pre-line">${ message }</p>
                </div>
                
                <div class="modal-action">
                    <button class="btn btn-ghost" onclick="document.getElementById('globalConfirmModal').close(); globalConfirmModalCancel()">
                        <span translate="no" class="material-symbols-outlined">close</span>
                        ${ cancelText }
                    </button>
                    <button class="btn btn-${ type === 'error' || type === 'danger' ? 'error' : 'primary' }" onclick="document.getElementById('globalConfirmModal').close(); globalConfirmModalConfirm()">
                        <span translate="no" class="material-symbols-outlined">check_circle</span>
                        ${ confirmText }
                    </button>
                </div>
            </div>
            <form method="dialog" class="modal-backdrop">
                <button>Fechar</button>
            </form>
        </dialog>
    `;

    document.body.insertAdjacentHTML('beforeend', modalHTML);

    window.globalConfirmModalConfirm = () => {
        onConfirm();
        delete window.globalConfirmModalConfirm;
        delete window.globalConfirmModalCancel;
        setTimeout(() => {
            document.getElementById('globalConfirmModal')?.remove();
        }, 100);
    };

    window.globalConfirmModalCancel = () => {
        onCancel();
        delete window.globalConfirmModalConfirm;
        delete window.globalConfirmModalCancel;
        setTimeout(() => {
            document.getElementById('globalConfirmModal')?.remove();
        }, 100);
    };

    document.getElementById('globalConfirmModal').showModal();
};

function getGlobalVars() {
    const globalVars = document?.querySelector('script[type="text/global-vars"]')?.innerText;

    if (!globalVars?.length) return;

    window.global = JSON.parse(globalVars);

    if (window.Alpine && window.Alpine.store) {
        Alpine.store('global', window.global);
    }

    document.dispatchEvent(new CustomEvent('globalVarsReady'));
}

window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', e => {
    processTheme();
})

document.addEventListener('themeChange', (event) => {
    const newTheme = event.detail.value;
    
    if (window.global?.isLoggedIn) {
        fetch('/account/theme', {
            method: 'PUT',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({ theme: newTheme })
        }).then(r => r.json()).then(data => {
            window.global.theme = newTheme;
            processTheme();
            window.showToast('Tema atualizado com sucesso', 'success');
        }).catch(err => {
            window.showToast('Erro ao atualizar tema', 'error');
        });
    } else {
        document.cookie = `theme=${newTheme}; path=/; max-age=31536000`;
        window.global.theme = newTheme;
        processTheme();
    }
})

window.prepareSystem = (number) => {
    fetch('/prepare?number=' + parseInt(number)).then(r => {
    });
}

function processTheme() {
    const theme = window.global?.theme || 'system';
    const themeLightConfig = window.global?.themeLightConfig || 'light';
    const themeDarkConfig = window.global?.themeDarkConfig || 'dark';
    
    if (theme === 'system') {
        if (window.matchMedia('(prefers-color-scheme: dark)').matches) {
            document.documentElement.dataset.theme = themeDarkConfig;
        } else {
            document.documentElement.dataset.theme = themeLightConfig;
        }

        window.matchMedia('(prefers-color-scheme: dark)').onchange = function (e) {
            if (e.matches) {
                document.documentElement.dataset.theme = themeDarkConfig;
            }
        }

        window.matchMedia('(prefers-color-scheme: light)').onchange = function (e) {
            if (e.matches) {
                document.documentElement.dataset.theme = themeLightConfig;
            }
        }
    } else if (theme === 'dark') {
        document.documentElement.dataset.theme = themeDarkConfig;
    } else {
        document.documentElement.dataset.theme = themeLightConfig;
    }
}

document.addEventListener("DOMContentLoaded", async () => {
    document.body.addEventListener('htmx:beforeSwap', function (evt) {
        if (evt.detail.xhr.status === 207) {
            evt.detail.shouldSwap = true;
            evt.detail.isError = false;

            document.querySelectorAll('[hx-swap-oob="true"]').forEach(function (el) {
                el.innerHTML = '';
            });

            getGlobalVars();
        }

        // Capturar HX-Trigger para showToast em respostas de erro
        const hxTrigger = evt.detail.xhr.getResponseHeader('HX-Trigger');
        if (hxTrigger) {
            try {
                const triggers = JSON.parse(hxTrigger);
                if (triggers.showToast) {
                    window.flash(triggers.showToast.message, triggers.showToast.type);
                }
            } catch (e) {
                // Ignorar erros de parsing
            }
        }
    });

    document.body.addEventListener('htmx:afterSwap', function (evt) {
        const oobElements = document.querySelectorAll('[hx-swap-oob]');

        oobElements.forEach(element => {
            element.removeAttribute('hx-swap-oob');
        });

        getGlobalVars();
    });

    document.body.addEventListener('prepareSystem', (e) => {
        let number = parseInt(e.detail.value);
        window.prepareSystem(number);
    });

    getGlobalVars();
    processTheme();
});

// Page loading indicator - intercepta navegação de links
(function() {
    const loadingBar = document.getElementById('page-loading-bar');
    if (!loadingBar) return;

    let isNavigating = false;
    let hideTimeout = null;

    function showLoading() {
        if (hideTimeout) {
            clearTimeout(hideTimeout);
            hideTimeout = null;
        }
        isNavigating = true;
        loadingBar.style.opacity = '1';
    }

    function hideLoading() {
        hideTimeout = setTimeout(() => {
            isNavigating = false;
            loadingBar.style.opacity = '0';
        }, 200);
    }

    // Expor hideLoading globalmente para ser chamado pelo toast
    window.hidePageLoading = hideLoading;

    // Intercepta cliques em links
    document.addEventListener('click', (e) => {
        const link = e.target.closest('a');
        if (!link) return;

        const href = link.getAttribute('href');
        if (!href || href.startsWith('#') || href.startsWith('javascript:')) return;
        if (link.target === '_blank') return;
        if (link.hasAttribute('download')) return;
        
        // Links externos
        try {
            const url = new URL(href, window.location.origin);
            if (url.origin !== window.location.origin) return;
        } catch (e) {
            // URL relativo, continua
        }

        showLoading();
    });

    // Intercepta form submits
    document.addEventListener('submit', (e) => {
        if (e.target.hasAttribute('hx-post') || 
            e.target.hasAttribute('hx-get') ||
            e.target.hasAttribute('hx-put') ||
            e.target.hasAttribute('hx-delete')) {
            return; // HTMX cuida disso
        }
        showLoading();
    });

    // Intercepta popstate (botão voltar/avançar)
    window.addEventListener('popstate', () => {
        showLoading();
    });

    // Esconde quando página carrega
    window.addEventListener('pageshow', () => {
        hideLoading();
    });

    // Esconde se DOMContentLoaded disparar (navegação completa)
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', hideLoading);
    } else {
        hideLoading();
    }
})();

// Verifica se há flash messages pendentes no sessionStorage
const pendingFlash = sessionStorage.getItem('pendingFlash');
if (pendingFlash) {
    sessionStorage.removeItem('pendingFlash');
    const { text, type } = JSON.parse(pendingFlash);
    // Aguarda um frame para garantir que o DOM esteja pronto
    requestAnimationFrame(() => window.flash(text, type));
}

// Listener para eventos toast - DEVE estar fora do DOMContentLoaded
document.body.addEventListener('toast', (e) => {
    // Cancelar loading quando toast aparecer
    if (window.hidePageLoading) {
        window.hidePageLoading();
    }

    let toast = JSON.parse(e.detail.value);

    const genericStyle = 'flex flex-row gap-2 border-2 !shadow-md !rounded-full !px-4 !py-2';

    const toastStyle = {
        error: genericStyle + ' !bg-red-500 border-red-700',
        success: genericStyle + ' !bg-green-500 border-green-700',
        warn: genericStyle + ' !bg-yellow-500 border-yellow-700',
    };

    // Verificar se há um dialog/modal aberto e usar como container
    const openDialog = document.querySelector('dialog[open]');
    
    Toastify({
        text: toast.text,
        duration: 4000,
        destination: false,
        newWindow: false,
        close: true,
        gravity: "top",
        position: "center",
        stopOnFocus: true,
        className: toastStyle[toast.type] ?? toastStyle.error,
        selector: openDialog || undefined,
        style: {
            background: 'none',
            zIndex: '2147483647'
        },
        onClick: function () {
        }
    }).showToast();
});

Alpine.start()
