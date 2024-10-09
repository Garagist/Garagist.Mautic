import manifest from "@neos-project/neos-ui-extensibility";

import EmailModuleEditor from "./EmailModuleEditor";

manifest("Garagist.Mautic:EmailModuleEditor", {}, (globalRegistry) => {
    const editorsRegistry = globalRegistry.get("inspector").get("editors");

    editorsRegistry.set("Garagist.Mautic/Inspector/Editors/EmailModule", {
        component: EmailModuleEditor,
        hasOwnLabel: true,
    });
});
