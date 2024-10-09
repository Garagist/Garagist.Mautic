import React from "react";
import { Button, Icon, Label } from "@neos-project/react-ui-components";
import I18n from "@neos-project/neos-ui-i18n";

function EmailModuleEditor(props) {
    const { label, className, identifier, options, renderHelpIcon, renderSecondaryInspector } = props;
    const { src, icon, name, disabled}  = options;

    return (
        <div style={{display:"flex"}}>
            <Label htmlFor={identifier}>
                <Button
                    className={className}
                    disabled={disabled}
                    style="lighter"
                    onClick={() => {
                        renderSecondaryInspector("IFRAME", () => (
                            <iframe
                                style={{ height: "100%", width: "100%", border: 0 }}
                                name={name || "email-module"}
                                src={
                                    src.startsWith("ClientEval:")
                                        ? (0, eval)(src.replace("ClientEval:", ""))
                                        : src
                                }
                            />
                        ))
                    }}
                >
                    {icon && <Icon icon={icon} padded="right" />}
                    <I18n id={label} />
                </Button>
            </Label>
            {renderHelpIcon && renderHelpIcon()}
        </div>
    );
}

export default EmailModuleEditor;
