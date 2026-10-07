import PropTypes from 'prop-types';
import { useEffect, useId, useMemo, useRef, useState } from 'react';
import FieldError from './FieldError';
import { rankOptions, splitMatch } from '../lib/search';

/**
 * Searchable multi-select over a fixed list of grouped options.
 * With an empty query the options are shown by group; while searching they are shown
 * as one list ordered by relevance (see lib/search.js), so the best match is first.
 * Follows the WAI-ARIA combobox + listbox pattern: type to filter,
 * ArrowUp/ArrowDown to move, Enter to toggle, Backspace to remove the last chip, Escape to close.
 */
export default function MultiSelectCombobox({
    label,
    options,
    value,
    onChange,
    max = Infinity,
    placeholder = 'Search…',
    hint,
    error,
    id,
    autoFocus = false,
}) {
    const generatedId = useId();
    const inputId = id || generatedId;
    const listboxId = `${inputId}-listbox`;
    const rootRef = useRef(null);
    const inputRef = useRef(null);
    const [query, setQuery] = useState('');
    const [open, setOpen] = useState(false);
    const [activeIndex, setActiveIndex] = useState(0);

    const byId = useMemo(() => new Map(options.map((option) => [option.value, option])), [options]);
    const selected = value.map((v) => byId.get(v)).filter(Boolean);
    const limitReached = value.length >= max;

    const searching = query.trim() !== '';
    const filtered = useMemo(() => (query.trim() ? rankOptions(options, query) : options), [options, query]);

    // Grouped when browsing; a single ranked list (group shown per option) when searching.
    const groups = useMemo(() => {
        if (searching) {
            return [['', filtered.map((option, index) => ({ option, index }))]];
        }
        const map = new Map();
        filtered.forEach((option, index) => {
            const key = option.group ?? '';
            if (!map.has(key)) {
                map.set(key, []);
            }
            map.get(key).push({ option, index });
        });
        return [...map.entries()];
    }, [filtered, searching]);

    const optionId = (index) => `${inputId}-option-${index}`;
    const active = filtered[Math.min(activeIndex, filtered.length - 1)];

    // Close when focus or clicks move outside the component.
    useEffect(() => {
        if (!open) {
            return undefined;
        }
        const onPointerDown = (event) => {
            if (rootRef.current && !rootRef.current.contains(event.target)) {
                setOpen(false);
            }
        };
        document.addEventListener('pointerdown', onPointerDown);
        return () => document.removeEventListener('pointerdown', onPointerDown);
    }, [open]);

    // Keep the active option visible while navigating with the keyboard.
    useEffect(() => {
        if (open && active) {
            document.getElementById(optionId(filtered.indexOf(active)))?.scrollIntoView({ block: 'nearest' });
        }
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [open, activeIndex]);

    const toggle = (option) => {
        if (value.includes(option.value)) {
            onChange(value.filter((v) => v !== option.value));
        } else if (!limitReached) {
            onChange([...value, option.value]);
            setQuery('');
            setActiveIndex(0);
        }
        inputRef.current?.focus();
    };

    const remove = (optionValue) => {
        onChange(value.filter((v) => v !== optionValue));
        inputRef.current?.focus();
    };

    const onKeyDown = (event) => {
        switch (event.key) {
            case 'ArrowDown':
                event.preventDefault();
                setOpen(true);
                setActiveIndex((i) => Math.min(i + 1, Math.max(filtered.length - 1, 0)));
                break;
            case 'ArrowUp':
                event.preventDefault();
                setOpen(true);
                setActiveIndex((i) => Math.max(i - 1, 0));
                break;
            case 'Home':
                if (open) {
                    event.preventDefault();
                    setActiveIndex(0);
                }
                break;
            case 'End':
                if (open) {
                    event.preventDefault();
                    setActiveIndex(Math.max(filtered.length - 1, 0));
                }
                break;
            case 'Enter':
                if (open && active) {
                    event.preventDefault();
                    toggle(active);
                }
                break;
            case 'Escape':
                if (open) {
                    event.preventDefault();
                    setOpen(false);
                } else if (query) {
                    setQuery('');
                }
                break;
            case 'Backspace':
                if (!query && value.length > 0) {
                    onChange(value.slice(0, -1));
                }
                break;
            case 'Tab':
                setOpen(false);
                break;
            default:
        }
    };

    const describedBy = [error && `${inputId}-error`, `${inputId}-status`, hint && `${inputId}-hint`].filter(Boolean).join(' ');

    return (
        <div ref={rootRef} className="relative">
            <label htmlFor={inputId} className="mb-1.5 block text-sm font-medium text-slate-700">
                {label}
            </label>

            <div
                className={`flex min-h-10 flex-wrap items-center gap-1.5 rounded-md bg-white px-2 py-1.5 shadow-sm ring-1 ring-inset focus-within:ring-2 ${
                    error ? 'ring-red-400 focus-within:ring-red-500' : 'ring-slate-300 focus-within:ring-indigo-600'
                }`}
                onClick={() => inputRef.current?.focus()}
            >
                {selected.length > 0 && (
                    <ul className="contents" aria-label="Selected">
                        {selected.map((option) => (
                            <li
                                key={option.value}
                                className="inline-flex max-w-full items-center gap-1 rounded-md bg-indigo-50 py-0.5 pl-2 pr-1 text-sm font-medium text-indigo-800 ring-1 ring-inset ring-indigo-200"
                            >
                                <span className="truncate">{option.label}</span>
                                <button
                                    type="button"
                                    onClick={(event) => {
                                        event.stopPropagation();
                                        remove(option.value);
                                    }}
                                    className="grid h-5 w-5 shrink-0 place-items-center rounded text-indigo-500 hover:bg-indigo-100 hover:text-indigo-800 focus-visible:outline-2 focus-visible:outline-indigo-600"
                                    aria-label={`Remove ${option.label}`}
                                >
                                    <svg className="h-3.5 w-3.5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                        <path d="M6.28 5.22a.75.75 0 00-1.06 1.06L8.94 10l-3.72 3.72a.75.75 0 101.06 1.06L10 11.06l3.72 3.72a.75.75 0 101.06-1.06L11.06 10l3.72-3.72a.75.75 0 00-1.06-1.06L10 8.94 6.28 5.22z" />
                                    </svg>
                                </button>
                            </li>
                        ))}
                    </ul>
                )}
                <input
                    ref={inputRef}
                    id={inputId}
                    type="text"
                    role="combobox"
                    autoComplete="off"
                    autoFocus={autoFocus}
                    aria-expanded={open}
                    aria-controls={listboxId}
                    aria-autocomplete="list"
                    aria-activedescendant={open && active ? optionId(filtered.indexOf(active)) : undefined}
                    aria-invalid={error ? true : undefined}
                    aria-describedby={describedBy}
                    value={query}
                    placeholder={limitReached ? '' : selected.length ? 'Add another…' : placeholder}
                    onChange={(event) => {
                        setQuery(event.target.value);
                        setActiveIndex(0);
                        setOpen(true);
                    }}
                    onFocus={() => setOpen(true)}
                    onKeyDown={onKeyDown}
                    className="min-w-32 flex-1 border-0 bg-transparent p-1 text-sm text-slate-900 placeholder:text-slate-400 focus:outline-none focus:ring-0"
                />
            </div>

            <p id={`${inputId}-status`} className="sr-only" aria-live="polite">
                {selected.length} of {max} selected{limitReached ? '. Maximum reached.' : '.'}
            </p>

            {open && (
                <div className="absolute z-20 mt-1 w-full overflow-hidden rounded-md bg-white shadow-lg ring-1 ring-slate-200">
                    {limitReached && (
                        <p className="border-b border-slate-100 bg-amber-50 px-3 py-2 text-xs text-amber-800">
                            You can choose up to {max}. Remove one to pick another.
                        </p>
                    )}
                    <ul
                        id={listboxId}
                        role="listbox"
                        aria-multiselectable="true"
                        aria-label={typeof label === 'string' ? label : 'Options'}
                        className="max-h-72 overflow-y-auto py-1"
                    >
                        {filtered.length === 0 && (
                            <li className="px-3 py-6 text-center text-sm text-slate-500" role="presentation">
                                No matches for “{query}”. Try a broader word, like “developer”, “nurse”, or “teacher”.
                            </li>
                        )}
                        {groups.map(([group, items], groupIndex) => (
                            <li key={group || 'all'} role="presentation">
                                {group && (
                                    <p
                                        id={`${inputId}-group-${groupIndex}`}
                                        className="sticky top-0 bg-slate-50 px-3 py-1.5 text-xs font-semibold uppercase tracking-wide text-slate-500"
                                    >
                                        {group}
                                    </p>
                                )}
                                <ul role="group" aria-labelledby={group ? `${inputId}-group-${groupIndex}` : undefined}>
                                    {items.map(({ option, index }) => {
                                        const isSelected = value.includes(option.value);
                                        const isDisabled = !isSelected && limitReached;
                                        const isActive = active === option;

                                        return (
                                            <li
                                                key={option.value}
                                                id={optionId(index)}
                                                role="option"
                                                aria-selected={isSelected}
                                                aria-disabled={isDisabled || undefined}
                                                onPointerDown={(event) => event.preventDefault()}
                                                onClick={() => !isDisabled && toggle(option)}
                                                onPointerMove={() => activeIndex !== index && setActiveIndex(index)}
                                                className={`flex cursor-pointer items-center justify-between gap-2 px-3 py-2 text-sm ${
                                                    isActive ? 'bg-indigo-50' : ''
                                                } ${isDisabled ? 'cursor-not-allowed text-slate-400' : 'text-slate-800'}`}
                                            >
                                                <span className="min-w-0">
                                                    <OptionLabel label={option.label} query={searching ? query : ''} />
                                                    {searching && option.group && (
                                                        <span className="ml-2 text-xs text-slate-400">{option.group}</span>
                                                    )}
                                                </span>
                                                {isSelected && (
                                                    <svg className="h-4 w-4 shrink-0 text-indigo-600" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                                        <path
                                                            fillRule="evenodd"
                                                            d="M16.704 4.153a.75.75 0 01.143 1.052l-8 10.5a.75.75 0 01-1.127.075l-4.5-4.5a.75.75 0 011.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 011.05-.143z"
                                                            clipRule="evenodd"
                                                        />
                                                    </svg>
                                                )}
                                            </li>
                                        );
                                    })}
                                </ul>
                            </li>
                        ))}
                    </ul>
                </div>
            )}

            {hint && !error && (
                <p id={`${inputId}-hint`} className="mt-1.5 text-xs text-slate-500">
                    {hint}
                </p>
            )}
            <FieldError id={`${inputId}-error`} message={error} />
        </div>
    );
}

function OptionLabel({ label, query }) {
    const [before, match, after] = splitMatch(label, query);

    if (!match) {
        return <span>{label}</span>;
    }

    return (
        <span>
            {before}
            <strong className="font-semibold text-slate-950">{match}</strong>
            {after}
        </span>
    );
}

OptionLabel.propTypes = {
    label: PropTypes.string.isRequired,
    query: PropTypes.string,
};

MultiSelectCombobox.propTypes = {
    label: PropTypes.node.isRequired,
    options: PropTypes.arrayOf(
        PropTypes.shape({
            value: PropTypes.oneOfType([PropTypes.string, PropTypes.number]).isRequired,
            label: PropTypes.string.isRequired,
            group: PropTypes.string,
            /** Lower ranks win ties between equally good matches (e.g. popularity). */
            rank: PropTypes.number,
        }),
    ).isRequired,
    value: PropTypes.array.isRequired,
    onChange: PropTypes.func.isRequired,
    max: PropTypes.number,
    placeholder: PropTypes.string,
    hint: PropTypes.node,
    error: PropTypes.string,
    id: PropTypes.string,
    autoFocus: PropTypes.bool,
};
