import {ActionIcon, NumberInput, NumberInputHandlers, Select, TextInputProps} from "@mantine/core";
import {useEffect, useRef, useState} from "react";
import {UseFormReturnType} from "@mantine/form";
import {IconMinus, IconPlus} from "@tabler/icons-react";
import {t} from "@lingui/macro";
import classes from './NumberSelector.module.scss';
import classNames from "classnames";

interface NumberSelectorProps extends TextInputProps {
    formInstance: UseFormReturnType<any>;
    fieldName: string,
    min?: number;
    max?: number;
    sharedValues?: SharedValues;
    selectorSize?: 'default' | 'compact';
    onLimitReached?: () => void;
}

const getFormValue = (values: Record<string, any>, fieldName: string): number | undefined => {
    const raw = fieldName.split('.').reduce<any>((acc, key) => acc?.[key], values);
    return raw !== undefined && raw !== null ? Number(raw) : undefined;
};

export const NumberSelector = ({formInstance, fieldName, min, max, sharedValues, selectorSize = 'default', onLimitReached}: NumberSelectorProps) => {
    const handlers = useRef<NumberInputHandlers>(null);
    const minValue = min !== undefined ? min : 0;
    const maxValue = max !== undefined ? max : 100;

    const initialQuantity = min !== undefined ? min : 1;
    const [value, setValue] = useState<number>(() => {
        const existing = getFormValue(formInstance.values, fieldName);
        return existing !== undefined ? existing : initialQuantity;
    });

    const [sharedVals] = useState<SharedValues>(() => {
        const shared = sharedValues ?? new SharedValues(maxValue);
        shared.changeValue(value);
        return shared;
    });

    useEffect(() => {
        formInstance.setFieldValue(fieldName, value);
    }, [value]);

    useEffect(() => {
        const formValue = getFormValue(formInstance.values, fieldName);
        if (formValue !== undefined && formValue !== value) {
            const adjustedDifference = sharedVals.changeValue(formValue - value);
            setValue(value + adjustedDifference);
        }
    }, [formInstance.values]);

    const increment = () => {
        // Adjust from 0 to minValue on the first increment, if minValue is greater than 1
        if (value === 0 && minValue > 1) {
            let adjustedMinimum = Math.max(1, minValue - sharedVals.currentValue)
            setValue(sharedVals.changeValue(Math.min(adjustedMinimum, maxValue, sharedVals.quantityRemaining)))
        } else if (sharedVals.currentValue < minValue) {
            const adjustedDifference = sharedVals.changeValue(minValue - sharedVals.currentValue);
            setValue(prevValue => prevValue + adjustedDifference);
        } else if (value < maxValue) {
            const adjustedDifference = sharedVals.changeValue(1);
            setValue(prevValue => prevValue + adjustedDifference);
        }
    };

    const decrement = () => {
        // Ensure decrement does not bring the current shared value between 0 and minValue
        if (sharedVals.currentValue > minValue) {
            const adjustedDifference = sharedVals.changeValue(-1);
            setValue(prevValue => prevValue + adjustedDifference);
        } else {
            sharedVals.changeValue(-value)
            setValue(0);
        }
    };

    const changeValue = (newValue: number) => {
        let adjustedDifference = sharedVals.changeValue(newValue - value);
        setValue(value + adjustedDifference);
    };

    const atMax = value >= maxValue || sharedVals.quantityRemaining == 0;

    const handleIncrement = () => {
        if (atMax) {
            onLimitReached?.();
            return;
        }
        increment();
    };

    const isEmpty = value === 0;
    const buttonSize = selectorSize === 'compact'
        ? (isEmpty ? 30 : 26)
        : (isEmpty ? 38 : 30);
    const iconSize = selectorSize === 'compact'
        ? (isEmpty ? 14 : 13)
        : (isEmpty ? 16 : 15);

    return (
        <div className={classNames(classes.wrapper, 'button-input', selectorSize === 'compact' && classes.compact)}
             data-empty={value === 0 || undefined}>
            {value > 0 && (
                <>
                    <ActionIcon
                        size={buttonSize}
                        radius={999}
                        variant={'transparent'}
                        onClick={decrement}
                        aria-label={t`Decrease quantity`}
                        onMouseDown={(event) => event.preventDefault()}
                        className={classNames(classes.control, classes.decrement)}
                    >
                        <IconMinus size={iconSize} stroke={2}/>
                    </ActionIcon>

                    <NumberInput
                        mb={0}
                        variant="unstyled"
                        min={0}
                        max={maxValue}
                        handlersRef={handlers}
                        value={value}
                        hideControls
                        onChange={(newValue) => changeValue(Number(newValue) || 0)}
                        aria-label={t`Quantity`}
                        classNames={{input: classes.input}}
                        style={{fontWeight: "900", fontSize: "12pt"}}
                    />
                </>
            )}

            <ActionIcon
                size={buttonSize}
                radius={999}
                variant={'transparent'}
                onClick={handleIncrement}
                aria-label={t`Increase quantity`}
                aria-disabled={atMax}
                data-limit={atMax || undefined}
                onMouseDown={(event) => event.preventDefault()}
                className={classNames(classes.control, classes.increment)}
            >
                <IconPlus size={iconSize} stroke={2}/>
            </ActionIcon>
        </div>
    );
}

/* todo: create an event setting to choose select over button */
export const NumberSelectorSelect = ({formInstance, fieldName, min, max, className}: NumberSelectorProps) => {
    const [value, setValue] = useState<string>('0');

    const minValue = min || 0;
    const maxValue = max || 100;

    useEffect(() => {
        // Only synchronize with form if the value is within bounds and not 0
        if (value !== '0') {
            formInstance.setFieldValue(fieldName, value);
        }
    }, [value, formInstance, fieldName]);

    let data = Array.from({length: maxValue - minValue + 1}, (_, i) => ({
        label: String(minValue + i),
        value: String(minValue + i),
    }));

    if (minValue > 0) {
        data = [{label: '0', value: '0'}, ...data];
    }

    return (
        <div className={classNames(classes.wrapper, 'select-input')}>
            <Select
                classNames={{
                    input: classes.input,
                }}
                className={className}
                onChange={(value) => setValue(value ?? '0')} // Ensure the value is set correctly on change
                value={value}
                data={data}
                checkIconPosition="right"
            />
        </div>
    );
}

// Used to aggregate related NumberSelectors together, to allow them to share a common maximum
// and know about the collective values of all the selectors
export class SharedValues {
    sharedMax: number;
    currentValue: number;

    constructor(sharedMax: number, initialValue: number = 0) {
        this.sharedMax = sharedMax;
        this.currentValue = initialValue;
    }

    get quantityRemaining() {
        return this.sharedMax - this.currentValue;
    }

    changeValue(difference: number) {
        let adjustedDifference = Math.min(difference, this.sharedMax - this.currentValue);
        this.currentValue += adjustedDifference;

        return adjustedDifference;
    }
}

