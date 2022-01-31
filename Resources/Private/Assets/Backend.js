import Alpine from "alpinejs";

if (window.name == "email-module") {
  document.documentElement.classList.add("email-module-integrated");
}

function docReady(fn) {
  // see if DOM is already available
  if (
    document.readyState === "complete" ||
    document.readyState === "interactive"
  ) {
    // call on next available tick
    setTimeout(fn, 1);
  } else {
    document.addEventListener("DOMContentLoaded", fn);
  }
}

docReady(() => Alpine.start());
