import Alpine from "alpinejs";
import collapse from "@alpinejs/collapse";
import focus from "@alpinejs/focus";
import dialog from "./Dialog";
import tippy, { createSingleton } from "tippy.js";

Alpine.plugin(collapse);
Alpine.plugin(focus);
Alpine.plugin(dialog);

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
        zIndex: parseInt(expression) || 9999,
        content: content(el),
        delay,
    });
});

// Directive: x-tooltips
Alpine.directive("tooltips", (el, { expression }) => {
    const zIndex = parseInt(expression) || 9999;
    const instances = [...el.querySelectorAll("[aria-label]")].map((element) =>
        tippy(element, { content: content(element), delay })
    );
    createSingleton(instances, {
        delay: 500,
        moveTransition: "transform 0.2s ease-out",
        zIndex,
    });
});

const dateNow = Date.now;
const raf = window.requestAnimationFrame;
const rafTimeOut = (callback, delay) => {
    let start = dateNow();
    let stop = false;
    const timeoutFunc = () => {
        dateNow() - start < delay ? stop || raf(timeoutFunc) : callback();
    };
    raf(timeoutFunc);
};

Alpine.data("actions", (minItems) => ({
    init() {
        const items = this.$root.querySelectorAll("a,button").length;
        if (items < minItems) {
            rafTimeOut(() => window.location.reload(), 5000);
        }
    },
}));

// Easier debuging
window.Alpine = Alpine;

docReady(() => Alpine.start());
