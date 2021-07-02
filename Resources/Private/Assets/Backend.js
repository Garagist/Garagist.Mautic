import Alpine from "alpinejs";

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

docReady(() => {
  if (window.name == "email-module") {
    document.body.classList.add("email-module-integrated");
    const sheet = document.createElement("style");
    sheet.innerText =
      ".email-module-integrated #neos-top-bar,.email-module-integrated .neos-menu,.email-module-integrated .neos-breadcrumb{display:none !important}.email-module-integrated.neos.neos-module>.neos-module-wrap{padding:40px 20px !important}.email-module-integrated.neos.neos-module .neos-footer{margin-left:-20px;margin-right:-20px}";
    document.head.appendChild(sheet);
  }

  Alpine.start();
});
