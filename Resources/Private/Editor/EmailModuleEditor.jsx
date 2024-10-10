import React from "react";
import { Button, Icon, TextArea } from "@neos-project/react-ui-components";
import { neos } from "@neos-project/neos-ui-decorators";
import Markdown from "markdown-to-jsx";

const neosifier = neos((globalRegistry) => ({
    i18nRegistry: globalRegistry.get("i18n"),
}));

const defaultOptions = {
    // General options
    disabled: false,

    // TextArea options
    showTextarea: true,
    maxlength: null,
    readonly: false,
    placeholder: "",
    minRows: 2,
    maxRows: 24,
    expandedRows: 6,
    help: "Garagist.Mautic:NodeTypes.Mixin.Email:properties.mauticPreviewText.options.help",

    // This is for the Button and the iframe
    // If src is null, the button will not show up
    showButton: true,
    src: null,
    icon: "paper-plane",
    name: "email-module",
    moduleLabel: "Garagist.Mautic:NodeTypes.Mixin.Email:properties.mauticPreviewText.options.moduleLabel",

    // Description text below everything
    showDescription: true,
    description: "Garagist.Mautic:NodeTypes.Mixin.Email:properties.mauticPreviewText.options.description",
};

const markdownStyle = (opacity = null) => ({
    fontSize: "var(--fontSize-Small)",
    lineHeight: 1.3,
    opacity,
});

function EmailModuleEditor(props) {
    const { id, value, commit, className, identifier, options, renderSecondaryInspector, i18nRegistry } = props;
    const CONFIG = { ...defaultOptions, ...options };
    const { disabled, src, icon, help, description, moduleLabel, placeholder } = CONFIG;

    return (
        <>
            {CONFIG.showTextarea && (
                <>
                    <TextArea
                        id={id}
                        value={value === null ? "" : value}
                        className={className}
                        onChange={commit}
                        disabled={disabled}
                        maxLength={CONFIG.maxlength}
                        readOnly={CONFIG.readonly}
                        placeholder={placeholder && i18nRegistry.translate(unescape(placeholder))}
                        minRows={CONFIG.minRows}
                        maxRows={CONFIG.maxRows}
                        expandedRows={CONFIG.expandedRows}
                    />
                    {help && <Markdown style={markdownStyle(0.8)} children={i18nRegistry.translate(help)} />}
                </>
            )}

            {CONFIG.showButton && src && moduleLabel && (
                <div style={{ margin: "2em 0" }}>
                    <Button
                        disabled={disabled}
                        style="lighter"
                        onClick={() => {
                            renderSecondaryInspector("IFRAME", () => (
                                <iframe
                                    style={{
                                        height: "100%",
                                        width: "100%",
                                        border: 0,
                                    }}
                                    name={CONFIG.name}
                                    src={
                                        src.startsWith("ClientEval:") ? (0, eval)(src.replace("ClientEval:", "")) : src
                                    }
                                />
                            ));
                        }}
                    >
                        {icon && <Icon icon={icon} padded="right" />}
                        <span>{i18nRegistry.translate(moduleLabel)}</span>
                    </Button>
                </div>
            )}

            {CONFIG.showDescription && description && (
                <Markdown style={markdownStyle()} children={i18nRegistry.translate(description)} />
            )}
        </>
    );
}

export default neosifier(EmailModuleEditor);
