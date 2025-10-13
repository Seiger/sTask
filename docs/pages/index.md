---
id: intro
title: sTask for Evolution CMS
slug: /
sidebar_position: 1
---

![sTask Logo](https://github.com/user-attachments/assets/sTask-logo)

[![Latest Stable Version](https://img.shields.io/packagist/v/seiger/stask?label=version)](https://packagist.org/packages/seiger/stask)
[![CMS Evolution](https://img.shields.io/badge/CMS-Evolution-brightgreen.svg)](https://github.com/evolution-cms/evolution)
![PHP version](https://img.shields.io/packagist/php-v/seiger/stask)
[![License](https://img.shields.io/packagist/l/seiger/stask)](https://packagist.org/packages/seiger/stask)
[![Issues](https://img.shields.io/github/issues/Seiger/stask)](https://github.com/Seiger/stask/issues)
[![Stars](https://img.shields.io/packagist/stars/Seiger/stask)](https://packagist.org/packages/seiger/stask)
[![Total Downloads](https://img.shields.io/packagist/dt/seiger/stask)](https://packagist.org/packages/seiger/stask)

## Welcome to sTask!

**sTask** is a powerful asynchronous task management system designed specifically for Evolution CMS. 
It provides a robust framework for creating, executing, and monitoring background tasks with automatic 
worker discovery and comprehensive logging capabilities.

Whether you need to process large data imports, generate reports, send emails in bulk, or perform 
any other time-consuming operations, **sTask** gives you the tools to handle these tasks efficiently 
without blocking your main application.

👉 Start with **[Getting Started](./getting-started.md)**.

## Features

- [x] **Asynchronous Task Management**
  - [x] Create and execute background tasks
  - [x] Task priority system (low, normal, high)
  - [x] Automatic retry mechanism with configurable attempts
  - [x] Task progress tracking (0-100%)
  - [x] Task status monitoring (pending, running, completed, failed, cancelled)

- [x] **Worker System**
  - [x] Automatic worker discovery from installed packages
  - [x] Worker registration and activation/deactivation
  - [x] Worker validation and error handling
  - [x] Custom worker implementation interface

- [x] **File-based Logging**
  - [x] Comprehensive task execution logs
  - [x] Log filtering by level (info, warning, error)
  - [x] Log download and management
  - [x] Automatic log cleanup

- [x] **Admin Interface**
  - [x] Dashboard with task statistics
  - [x] Worker management panel
  - [x] Real-time task monitoring
  - [x] Task execution controls

- [x] **Integration**
  - [x] Evolution CMS manager integration
  - [x] Menu integration with custom logo
  - [x] Artisan commands for task management
  - [x] Composer package with auto-assets publishing

- [ ] **Future Features**
  - [ ] Task scheduling with cron integration
  - [ ] Task dependencies and workflow management
  - [ ] Email notifications for task completion
  - [ ] Task performance metrics and analytics
  - [ ] Webhook support for external integrations
  - [ ] Task templates and presets
  - [ ] Multi-server task distribution
  - [ ] Task queue prioritization algorithms

## Requirements

- Evolution CMS **3.2.0+**
- PHP **8.2+**
- Composer **2.2+**
- One of: **MySQL 8.0+** / **MariaDB 10.5+** / **PostgreSQL 10+** / **SQLite 3.25+**

## Support

If you need help, please don't hesitate to **[open an issue](https://github.com/Seiger/sTask/issues)**.