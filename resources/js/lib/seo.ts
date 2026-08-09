export const formatPageTitle = (title: string, appName: string): string => {
    const normalizedTitle = title.trim();
    const normalizedAppName = appName.trim();

    if (!normalizedTitle) return normalizedAppName;
    if (!normalizedAppName || normalizedTitle.includes(normalizedAppName)) return normalizedTitle;

    return `${normalizedTitle} | ${normalizedAppName}`;
};
