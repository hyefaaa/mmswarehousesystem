# MMS WMS System (Moo Moo Supplies Warehouse Management System)

MMS WMS is a customized, lightweight, and highly extensible **Warehouse Management System (WMS)** specifically designed for **Moo Moo Supplies (MMS)** to manage general commercial product distribution alongside the automated logistics tracking for the national school milk contract, **Program Susu Sekolah (PSS)**.

---

## Architecture & Role-Based Access Control (RBAC)

The system is built on a modular web-based **Monolithic Business-Component Architecture**. Business functions are grouped together as highly cohesive modules and restricted using a dynamic **Role-Based Access Control (RBAC)** model:

* **Admin (Full Access)**: Manages staff accounts, maps user logins to their corresponding Hub Dealers, monitors the system audit trail, configures product master files, and imports bulk contract master registries.
* **Staff (Warehouse Operations)**: Executes physical warehouse operations including product receiving (Inbound), batch relocations (Stock Transfer), inventory audits (Stock Take / Opname), reporting damaged goods (Spoilage), and processing commercial order dispatches (Commercial Outbound).
* **Dealer (Hub Dealer - PSS Only)**: A restricted access role tailored for contractors. They can only access PSS-related dispatch tools (PSS Delivery) to generate school delivery orders and track their schools' delivery status in the PSS Master Hub.

---

## Core Services & Functional Modules

The system is organized into four main business modules:

### 1. Warehouse & Inventory Service
* Handles inbound batch stock receiving (single-item barcode scan or bulk multi-item entry).
* Processes physical batch relocations between warehouse racks and zones.
* Performs physical stock-take adjustments to reconcile system counts with real warehouse stock.

### 2. Logistics & Outbound Service
* Manages commercial stock checkouts.
* Generates PSS Delivery Orders (DOs) automatically based on student enrollment, cycle quotas (TP1 & TP2), registered vehicle plates, and FIFO-based (First-In, First-Out) inventory batch matching.
* Keeps a unified, searchable log tracking both commercial and PSS shipments.

### 3. School Milk Contract Management (PSS)
* **PSS Master Hub**: Real-time delivery progress and schedule tracking dashboard for each Hub Dealer.
* **Monthly CO Import**: Allows admins to upload monthly Excel CO lists to automatically generate formatted SAP transaction files.
* **Batch Archives**: Stores generated monthly SAP reports for auditing and export.
* **Import Master PSS**: Admin-only tool to upload raw school registries.

### 4. Pallet Ledger Service
* Monitors real-time balances for **6 official pallet types** (Plain Wood, Loscam Red, LHP Green, FFM Orange, FFM Green, Plastic Black).
* Loaded pallets are dynamically calculated based on carton quantities divided by SKU pallet capacities, while empty pallets are logged upon dispatch or driver returns.

---

## Technologies

The system runs on a modern, high-performance web stack optimized for local server deployments (XAMPP / LAN) or standard cloud hosting environments:

* **Backend Engine**: PHP 8.x (utilizing secure PDO drivers to protect against SQL Injection).
* **Database Store**: MariaDB / MySQL (enforced with foreign key indexes and SQL transactional integrity).
* **Frontend Design**: Responsive HTML5, customized **Vanilla CSS** with a modern dark-mode header, Bootstrap 5, **jQuery** (for real-time quota adjustments), **Select2** (searchable dropdown fields), **Flatpickr** (modern date pickers), and **SheetJS (XLSX)** for client-side Excel imports.

---

## System Flowchart

```mermaid
graph TD
    %% Users
    Admin([Admin])
    Staff([Warehouse Staff])
    Dealer([Hub Dealer])

    %% System Core
    subgraph "MMS WMS Core System"
        direction TB
        %% Modules
        subgraph "Warehouse & Inventory"
            RCV[Inbound Receiving]
            ST[Stock Transfer]
            SA[Stock Take / Adjustments]
        end

        subgraph "Logistics & Outbound"
            CO[Commercial Outbound]
            PSS_DO[PSS Delivery Orders]
            LOG[Shipment Logs]
        end

        subgraph "PSS Contract Management"
            HUB[PSS Master Hub Dashboard]
            IMP[CO Import & SAP Generation]
            ARC[Batch Archives]
            MAS[Master Registry Upload]
        end

        subgraph "Pallet Ledger"
            PM[Pallet Balance Tracking]
        end
    end

    %% Database
    DB[(MariaDB / MySQL)]

    %% Access & Flows
    Admin -->|Full Access & Config| PSS Contract Management
    Admin -->|Oversight| Warehouse & Inventory
    Staff -->|Executes| Warehouse & Inventory
    Staff -->|Processes| Logistics & Outbound
    Staff -->|Updates| Pallet Ledger
    Dealer -->|Tracks Status| HUB
    Dealer -->|Generates DO| PSS_DO

    %% Data Flow
    Warehouse & Inventory <--> DB
    Logistics & Outbound <--> DB
    PSS Contract Management <--> DB
    Pallet Ledger <--> DB
```
