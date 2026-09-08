// ============================================
// [SECTION: MAIN ENTRY POINT]
// Runs once the page has fully loaded.
// ============================================
document.addEventListener("DOMContentLoaded", function () {

    // [SECTION: ELEMENT REFERENCES]
    const searchInput    = document.querySelector('input[name="search"]');
    const searchForm     = document.querySelector(".search-form");
    const categorySelect = document.querySelector('select[name="category"]');
    const locationSelect = document.querySelector('select[name="location"]');

    // [SECTION: LIVE SEARCH - auto-submit after the user stops typing]
    if (searchInput && searchForm) {
        let debounceTimer;
        searchInput.addEventListener("input", function () {
            clearTimeout(debounceTimer);
            debounceTimer = setTimeout(function () {
                searchForm.submit();
            }, 500);
        });
    }

    // [SECTION: CATEGORY FILTER - auto-submit on change]
    if (categorySelect && searchForm) {
        categorySelect.addEventListener("change", function () {
            searchForm.submit();
        });
    }

    // [SECTION: LOCATION FILTER - auto-submit on change]
    if (locationSelect && searchForm) {
        locationSelect.addEventListener("change", function () {
            searchForm.submit();
        });
    }

    // [SECTION: DELETE CONFIRMATION]
    // Extra safety net for delete buttons, in case inline onclick is stripped
    document.querySelectorAll(".btn-delete").forEach(function (btn) {
        btn.addEventListener("click", function (e) {
            if (!confirm("Delete this item? This cannot be undone.")) {
                e.preventDefault();
            }
        });
    });

    // ============================================
    // [SECTION: DYNAMIC EXPIRY DATE REQUIREMENT]
    // Toggles the "required" look on Expiry Date
    // based on the selected Category.
    // ============================================
    const categoryInput = document.getElementById("category-input");
    const expiryInput   = document.getElementById("expiry-input");
    const expiryLabel   = document.getElementById("expiry-label");
    const expiryRequiredCategories = ["Medicine", "Consumable"];

    // [SUBSECTION: UPDATE FUNCTION - runs whenever category changes]
    function updateExpiryRequirement() {
        if (!categoryInput || !expiryInput || !expiryLabel) return;
        const isRequired = expiryRequiredCategories.includes(categoryInput.value.trim());
        expiryInput.required = isRequired;
        expiryLabel.innerHTML = isRequired ? "Expiry Date *" : "Expiry Date";
        expiryLabel.classList.toggle("label-required", isRequired);
    }

    // [SUBSECTION: WIRE UP THE LISTENER]
    if (categoryInput) {
        categoryInput.addEventListener("input", updateExpiryRequirement);
        updateExpiryRequirement(); // run once on page load (e.g. when editing an item)
    }

    // ============================================
    // [SECTION: DYNAMIC SKU FIELD VISIBILITY]
    // SKU only makes sense for Equipment and Training Model
    // items, so the field is hidden entirely for other categories.
    // ============================================
    const skuField           = document.getElementById("sku-field");
    const skuApplicableCategories = ["Equipment", "Training Model"];

    // [SUBSECTION: UPDATE FUNCTION - runs whenever category changes]
    function updateSkuVisibility() {
        if (!categoryInput || !skuField) return;
        const isApplicable = skuApplicableCategories.includes(categoryInput.value.trim());
        skuField.style.display = isApplicable ? "" : "none";
        // Clear out any leftover SKU value when hiding the field,
        // so an old SKU can't silently get saved under a new category.
        const skuInput = skuField.querySelector('input[name="sku"]');
        if (!isApplicable && skuInput) {
            skuInput.value = "";
        }
    }

    // [SUBSECTION: WIRE UP THE LISTENER]
    if (categoryInput) {
        categoryInput.addEventListener("input", updateSkuVisibility);
        updateSkuVisibility(); // run once on page load (e.g. when editing an item)
    }

    // ============================================
    // [SECTION: DESKTOP SIDEBAR COLLAPSE TOGGLE]
    // Clicking the < / > button shrinks the sidebar to icon-only width
    // and remembers the choice in a cookie, so it stays collapsed (or
    // expanded) across page loads until toggled again. Mobile ignores
    // this entirely - it always uses the full-screen hamburger menu.
    // ============================================
    const sidebarEl    = document.querySelector(".sidebar");
    const collapseBtn  = document.getElementById("sidebar-collapse-btn");

    if (sidebarEl && collapseBtn) {
        collapseBtn.addEventListener("click", function () {
            const isNowCollapsed = sidebarEl.classList.toggle("collapsed");
            collapseBtn.textContent = isNowCollapsed ? "\u203A" : "\u2039";
            // Cookie lasts a year and applies site-wide (path=/)
            document.cookie = "sidebar_collapsed=" + (isNowCollapsed ? "1" : "0") + "; path=/; max-age=" + (60 * 60 * 24 * 365);
        });
    }
});
