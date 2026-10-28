/** Сериализованный Illuminate\Pagination\LengthAwarePaginator */
export type Paginated<T> = {
    data: T[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    from: number | null;
    to: number | null;
    links: {
        url: string | null;
        label: string;
        active: boolean;
    }[];
};

export type SelectOption<T extends string = string> = {
    value: T;
    label: string;
};
