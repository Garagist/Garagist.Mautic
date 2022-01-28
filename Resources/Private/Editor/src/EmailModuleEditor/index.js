import React, { PureComponent } from "react";
import PropTypes from "prop-types";
import { Button, Icon, Label } from "@neos-project/react-ui-components";
import I18n from "@neos-project/neos-ui-i18n";
import { $get } from "plow-js";

export default class EmailModuleEditor extends PureComponent {
    static propTypes = {
        className: PropTypes.string,
        identifier: PropTypes.string.isRequired,
        label: PropTypes.string.isRequired,
        options: PropTypes.object,
        renderHelpIcon: PropTypes.func,
        renderSecondaryInspector: PropTypes.func.isRequired,
    };

    render = () => {
        const { label, className, identifier, options, renderHelpIcon } = this.props;
        const disabled = $get("options.disabled", this.props);
        const { icon, src, name } = options;

        return (
            <div>
                <Label htmlFor={identifier}>
                    <Button
                        className={className}
                        disabled={disabled}
                        onClick={() => {
                            this.props.renderSecondaryInspector("IFRAME", () => {
                                return (
                                    <iframe
                                        style={{ height: "100%", width: "100%", border: 0 }}
                                        name={name || "email-module"}
                                        src={
                                            src.indexOf("ClientEval:") === 0
                                                ? eval(src.replace("ClientEval:", ""))
                                                : src
                                        }
                                    />
                                );
                            });
                        }}
                        style="lighter"
                    >
                        {icon ? <Icon icon={icon} padded="right" /> : null}
                        <I18n id={label} />
                    </Button>
                </Label>
                {renderHelpIcon ? renderHelpIcon() : null}
            </div>
        );
    };
}
