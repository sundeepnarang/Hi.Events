import {ActionIcon, Button, Checkbox} from "@mantine/core";
import {t} from "@lingui/macro";
import {IconShoppingCartDown, IconShoppingCartUp} from "@tabler/icons-react";
import classes from "./CheckoutFooter.module.scss";
import {Event, Order, Question} from "../../../../types.ts";
import {CheckoutSidebar} from "../CheckoutSidebar";
import {ReactNode, useState} from "react";
import classNames from "classnames";

const REQUIRED_CONSENT_QUESTION_TITLE = `Required Consent`;

interface ContinueButtonProps {
    isLoading: boolean;
    buttonContent?: ReactNode;
    order: Order;
    event: Event;
    orderQuestions: Question[];
    isOrderComplete?: boolean;
    onClick?: () => void;
}

export const CheckoutFooter = ({isLoading, buttonContent, event, order, orderQuestions, onClick, isOrderComplete = false}: ContinueButtonProps) => {
    console.log("event: ", event);
    console.log("order: ",order);
    console.log("orderQuestions: ",orderQuestions);
    const hasRequiredConsent = orderQuestions.some(d=>d.title==REQUIRED_CONSENT_QUESTION_TITLE);
    const [isSidebarOpen, setIsSidebarOpen] = useState(false);
    const [checked, setChecked] = useState(true);
    return (
        <>
            {isSidebarOpen && <div className={classes.overlay} onClick={() => setIsSidebarOpen(false)}/>}

            <div className={classNames(classes.footer, isOrderComplete ? classes.orderComplete : '')}>
                {isSidebarOpen && <CheckoutSidebar event={event} order={order} className={classes.sidebar}/>}
                {!isOrderComplete && !hasRequiredConsent && (
                    <div className={classes.buttons}>
                        <Checkbox
                            checked={checked}
                            onChange={(event) => setChecked(event.currentTarget.checked)}
                            label={t`I confirm that I am 18 years of age and consent to the collection and use of my information for this registration in accordance with Science of Spirituality’s Privacy Policy and Terms of Use. I agree to receive updates and communications related to this event and understand that I may opt out anytime.`}
                        />
                    </div>
                )}
                <div className={classes.buttons}>
                    {!isOrderComplete && (
                        <Button
                            className={classes.continueButton}
                            loading={isLoading}
                            size="md"
                            type="submit"
                            onClick={onClick}
                            disabled={!checked}
                        >
                            {buttonContent || t`Continue`}
                        </Button>
                    )}
                    <ActionIcon onClick={() => setIsSidebarOpen(!isSidebarOpen)}
                                variant={'transparent'}
                                size={'md'}
                                className={classes.orderSummaryToggle}
                    >
                        {isSidebarOpen && <IconShoppingCartDown stroke={2}/>}
                        {!isSidebarOpen && <IconShoppingCartUp stroke={2}/>}
                    </ActionIcon>
                </div>
            </div>
        </>
    );
}
