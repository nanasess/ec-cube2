# public.mtb_db

## Description

データベース種別

## Columns

| Name | Type | Default | Nullable | Children | Parents | Comment |
| ---- | ---- | ------- | -------- | -------- | ------- | ------- |
| id | smallint |  | false |  |  | ID |
| name | text |  | true |  |  | 名称 |
| rank | smallint | 0 | false |  |  | 表示順 |

## Constraints

| Name | Type | Definition |
| ---- | ---- | ---------- |
| mtb_db_pkey | PRIMARY KEY | PRIMARY KEY (id) |

## Indexes

| Name | Definition |
| ---- | ---------- |
| mtb_db_pkey | CREATE UNIQUE INDEX mtb_db_pkey ON public.mtb_db USING btree (id) |

## Relations

![er](public.mtb_db.svg)

---

> Generated by [tbls](https://github.com/k1LoW/tbls)