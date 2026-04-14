<?php

class Service
{
    private string $service_id;
    private string $title;
    private string $category;
    private string $subcategory;
    private float $price;
    private int $delivery_time;
    private int $revisions_included;
    private string $freelancer_id;
    private string $freelancer_name;
    private string $main_image_path;
    private int $added_to_cart_timestamp;

    public function __construct(
        string $service_id,
        string $title,
        string $category,
        string $subcategory,
        float $price,
        int $delivery_time,
        int $revisions_included,
        string $freelancer_id,
        string $freelancer_name,
        string $main_image_path,
        int $added_to_cart_timestamp
    ) {
        $this->service_id = $service_id;
        $this->title = $title;
        $this->category = $category;
        $this->subcategory = $subcategory;
        $this->price = $price;
        $this->delivery_time = $delivery_time;
        $this->revisions_included = $revisions_included;
        $this->freelancer_id = $freelancer_id;
        $this->freelancer_name = $freelancer_name;
        $this->main_image_path = $main_image_path;
        $this->added_to_cart_timestamp = $added_to_cart_timestamp;
    }

    public function getServiceId(): string { return $this->service_id; }
    public function getTitle(): string { return $this->title; }
    public function getCategory(): string { return $this->category; }
    public function getSubcategory(): string { return $this->subcategory; }

    public function getPrice(): float { return $this->price; }
    public function getFormattedPrice(): string { return "$" . number_format($this->price, 2); }

    public function getDeliveryTime(): int { return $this->delivery_time; }
    public function getFormattedDelivery(): string {
        return ($this->delivery_time === 1) ? "1 day" : $this->delivery_time . " days";
    }

    public function getRevisionsIncluded(): int { return $this->revisions_included; }

    public function getFreelancerId(): string { return $this->freelancer_id; }
    public function getFreelancerName(): string { return $this->freelancer_name; }

    public function getMainImagePath(): string { return $this->main_image_path; }
    public function getAddedToCartTimestamp(): int { return $this->added_to_cart_timestamp; }

    public function calculateServiceFee(): float {
        return round($this->price * 0.05, 2); // 5%
    }

    public function getTotalWithFee(): float {
        return round($this->price + $this->calculateServiceFee(), 2);
    }
}
