import Alpine from "alpinejs";
import tippy, { createSingleton } from "tippy.js";

if (window.name == "email-module") {
    document.documentElement.classList.add("email-module-integrated");
}

function docReady(fn) {
    // see if DOM is already available
    if (document.readyState === "complete" || document.readyState === "interactive") {
        // call on next available tick
        setTimeout(fn, 1);
    } else {
        document.addEventListener("DOMContentLoaded", fn);
    }
}

const delay = [500, 0];
const content = (element) => element.getAttribute("aria-label") || element.getAttribute("title");

// Directive: x-tooltip
Alpine.directive("tooltip", (el, { expression }) => {
    tippy(el, {
        placement: expression || "top",
        content: content(el),
        delay,
    });
});

// Directive: x-tooltips
Alpine.directive("tooltips", (el, { expression }) => {
    const placement = expression || "top";
    const instances = [...el.querySelectorAll("[aria-label]")].map((element) =>
        tippy(element, { placement, content: content(element), delay })
    );
    createSingleton(instances, {
        delay: 500,
        moveTransition: "transform 0.2s ease-out",
    });
});
docReady(() => Alpine.start());
