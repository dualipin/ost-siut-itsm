create table financial_reports_annual
(
    id       int auto_increment primary key,
    year     int  not null unique,
    document text not null
);