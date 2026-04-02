export type FormFeedbackErrors = Record<string, string | undefined>;

type NormalizeFormErrorsOptions = {
    prefix?: string;
};

export function normalizeFormErrors(
    errors: FormFeedbackErrors,
    options: NormalizeFormErrorsOptions = {},
): FormFeedbackErrors {
    const normalized: FormFeedbackErrors = {};
    const prefix = options.prefix ? `${options.prefix}.` : null;

    for (const [key, message] of Object.entries(errors)) {
        if (!message) {
            continue;
        }

        if (!prefix || !key.startsWith(prefix)) {
            normalized[key] = message;
            continue;
        }

        const normalizedKey = key.slice(prefix.length);

        if (!normalized[normalizedKey]) {
            normalized[normalizedKey] = message;
        }
    }

    return normalized;
}

export function humanizeErrorField(
    field: string,
    fieldLabels: Record<string, string> = {},
): string {
    if (fieldLabels[field]) {
        return fieldLabels[field];
    }

    return field
        .replace(/\./g, ' ')
        .replace(/_/g, ' ')
        .replace(/\b\w/g, (character) => character.toUpperCase());
}
