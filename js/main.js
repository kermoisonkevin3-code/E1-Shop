/**
 * E1 SHOP – JavaScript Principal
 * Gestion panier, UI, et appels AJAX sécurisés
 */

// ============================================================
// CHIFFREMENT SIMPLE côté client (obfuscation token)
// Pour la sécurité réelle : utiliser HTTPS + tokens CSRF serveur
// ============================================================
const E1Crypto = {
    // Encode en base64 les paramètres sensibles
    encode: (data) => btoa(unescape(encodeURIComponent(JSON.stringify(data)))),
    decode: (str) => {
        try { return JSON.parse(decodeURIComponent(escape(atob(str)))); }
        catch { return null; }
    },
    // Hash léger pour vérification intégrité (non-cryptographique, usage UI)
    simpleHash: (str) => {
        let hash = 0;
        for (let i = 0; i < str.length; i++) {
            hash = ((hash << 5) - hash) + str.charCodeAt(i);
            hash |= 0;
        }
        return Math.abs(hash).toString(16);
    }
};

// ============================================================
// PANIER
// ============================================================
async function addToCart(productId, quantity = 1) {
    const btn = document.querySelector(`[onclick="addToCart(${productId})"]`);
    if (btn) { btn.disabled = true; btn.textContent = '…'; }

    try {
        const token = document.querySelector('meta[name="csrf-token"]')?.content || '';
        const payload = E1Crypto.encode({ product_id: productId, qty: quantity, ts: Date.now() });

        const resp = await fetch('php/cart_action.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `action=add&payload=${encodeURIComponent(payload)}&csrf=${encodeURIComponent(token)}`
        });

        const data = await resp.json();

        if (data.success) {
            updateCartCount(data.cart_count);
            showToast('Produit ajouté au panier ✓', 'success');
        } else {
            showToast(data.message || 'Erreur, veuillez vous connecter', 'error');
            if (data.redirect) setTimeout(() => window.location.href = data.redirect, 1500);
        }
    } catch (err) {
        showToast('Erreur de connexion', 'error');
    } finally {
        if (btn) { btn.disabled = false; btn.textContent = '+ Panier'; }
    }
}

async function removeFromCart(productId) {
    if (!confirm('Retirer cet article du panier ?')) return;
    const payload = E1Crypto.encode({ product_id: productId, ts: Date.now() });
    const resp = await fetch('php/cart_action.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `action=remove&payload=${encodeURIComponent(payload)}`
    });
    const data = await resp.json();
    if (data.success) { updateCartCount(data.cart_count); location.reload(); }
}

async function updateQty(productId, delta) {
    const payload = E1Crypto.encode({ product_id: productId, delta, ts: Date.now() });
    const resp = await fetch('php/cart_action.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `action=update_qty&payload=${encodeURIComponent(payload)}`
    });
    const data = await resp.json();
    if (data.success) { updateCartCount(data.cart_count); location.reload(); }
}

function updateCartCount(count) {
    document.querySelectorAll('.cart-count').forEach(el => { el.textContent = count; });
}

// ============================================================
// TOAST NOTIFICATIONS
// ============================================================
function showToast(message, type = 'info') {
    const existing = document.querySelector('.e1-toast');
    if (existing) existing.remove();

    const toast = document.createElement('div');
    toast.className = `e1-toast e1-toast--${type}`;
    toast.textContent = message;
    toast.style.cssText = `
        position: fixed; bottom: 24px; right: 24px;
        background: ${type === 'success' ? '#27ae60' : type === 'error' ? '#e74c3c' : '#333'};
        color: white; padding: 14px 24px; border-radius: 12px;
        font-family: 'DM Sans', sans-serif; font-weight: 600; font-size: 0.9rem;
        box-shadow: 0 8px 30px rgba(0,0,0,0.2); z-index: 9999;
        animation: slideIn 0.3s cubic-bezier(.4,0,.2,1);
        max-width: 320px;
    `;
    document.body.appendChild(toast);
    setTimeout(() => {
        toast.style.animation = 'slideOut 0.3s forwards';
        setTimeout(() => toast.remove(), 300);
    }, 3000);
}

// ============================================================
// RECHERCHE LIVE (autocomplete)
// ============================================================
let searchTimeout;
const searchInput = document.querySelector('.nav-search input');
if (searchInput) {
    let dropdown = null;

    searchInput.addEventListener('input', function () {
        clearTimeout(searchTimeout);
        const q = this.value.trim();
        if (q.length < 2) { if (dropdown) dropdown.remove(); dropdown = null; return; }

        searchTimeout = setTimeout(async () => {
            const resp = await fetch(`php/search_suggest.php?q=${encodeURIComponent(q)}`);
            const data = await resp.json();
            if (dropdown) dropdown.remove();
            if (!data.results?.length) return;

            dropdown = document.createElement('div');
            dropdown.className = 'search-dropdown';
            dropdown.style.cssText = `
                position: absolute; top: 100%; left: 0; right: 0;
                background: white; border-radius: 12px;
                box-shadow: 0 8px 30px rgba(0,0,0,0.15); z-index: 200; overflow: hidden;
                margin-top: 4px;
            `;
            data.results.forEach(item => {
                const a = document.createElement('a');
                a.href = `produit.php?id=${item.id}`;
                a.textContent = item.nom;
                a.style.cssText = `display: block; padding: 12px 16px; font-size: 0.9rem; border-bottom: 1px solid #f5f5f5;`;
                a.addEventListener('mouseover', () => a.style.background = '#f8f5f0');
                a.addEventListener('mouseout', () => a.style.background = '');
                dropdown.appendChild(a);
            });

            const container = searchInput.closest('form').parentElement;
            container.style.position = 'relative';
            container.appendChild(dropdown);
        }, 300);
    });

    document.addEventListener('click', (e) => {
        if (!e.target.closest('.nav-search')) {
            if (dropdown) { dropdown.remove(); dropdown = null; }
        }
    });
}

// ============================================================
// ANIMATIONS AU SCROLL
// ============================================================
const observer = new IntersectionObserver((entries) => {
    entries.forEach(entry => {
        if (entry.isIntersecting) {
            entry.target.style.opacity = '1';
            entry.target.style.transform = 'translateY(0)';
        }
    });
}, { threshold: 0.1 });

document.querySelectorAll('.product-card, .cat-card').forEach(el => {
    el.style.opacity = '0';
    el.style.transform = 'translateY(20px)';
    el.style.transition = 'opacity 0.5s ease, transform 0.5s ease';
    observer.observe(el);
});

// ============================================================
// CSS ANIMATION KEYFRAMES (injectés dynamiquement)
// ============================================================
const style = document.createElement('style');
style.textContent = `
    @keyframes slideIn {
        from { opacity: 0; transform: translateY(20px); }
        to { opacity: 1; transform: translateY(0); }
    }
    @keyframes slideOut {
        from { opacity: 1; transform: translateY(0); }
        to { opacity: 0; transform: translateY(20px); }
    }
`;
document.head.appendChild(style);
