<?php

namespace tal\pizzaplug\tasks;

use dktapps\pmforms\FormIcon;
use dktapps\pmforms\MenuForm;
use dktapps\pmforms\MenuOption;
use pocketmine\player\Player;
use pocketmine\scheduler\AsyncTask;
use pocketmine\Server;
use pocketmine\utils\Internet;
use pocketmine\utils\TextFormat;
use ReflectionClass;
use tal\pizzaplug\User;

class AsyncMenuRequestTask extends AsyncTask
{

    public function __construct(
        public string $player,
        public int    $store,
        public User   $user
    ){}

    public function onRun(): void
    {
        $result = Internet::getURL("https://order.dominos.com/power/store/$this->store/menu?lang=en&structured=true");
        $this->setResult(json_decode($result->getBody(), true));
    }

    public function onCompletion(): void
    {
        $player = Server::getInstance()->getPlayerExact($this->player);
        if ($player !== null && $player->isOnline()) {
            $result = $this->getResult();
            $products = $result["Products"];
            $variants = $result["Variants"];

            // I deeply apologize for what your eyes are about to witness.
            // TODO: Clean up this massive shithole.
            $categories = $result["Categorization"]["Food"]["Categories"];
            $entries = [new MenuOption("View Cart")];
            foreach ($categories as $category) {
                $name = $category["Name"];
                if (strlen($name) === 0) {
                    // Seems to happen sometimes with things like chips.
                    $name = $category["Code"];
                }

                $icon = null;
                $categoryProducts = $category["Products"];
                if (count($categoryProducts) > 0) {
                    $icon = new FormIcon("https://cache.dominos.com/olo/6_47_2/assets/build/market/US/_en/images/img/products/larges/$categoryProducts[0].jpg");
                } else {
                    $subCategories = $category['Categories'];
                    foreach ($subCategories as $subCategory) {
                        $subCategoryProducts = $subCategory["Products"];
                        if (count($subCategoryProducts) > 0) {
                            $icon = new FormIcon("https://cache.dominos.com/olo/6_47_2/assets/build/market/US/_en/images/img/products/larges/$subCategoryProducts[0].jpg");
                            break;
                        }
                    }
                }

                $entries[$category['Code']] = new MenuOption($name, $icon);
            }

            $selectedItems = [];
            $categoryForm = new MenuForm("Domino's Menu", 'You currently have 0 items selected.', $entries, function (): void {
            });

            $reflection = new ReflectionClass($categoryForm);
            $onSubmit = $reflection->getProperty('onSubmit');
            $onSubmit->setAccessible(true);
            $onSubmit->setValue($categoryForm, function (Player $player, int $selected) use ($reflection, $variants, $categoryForm, &$selectedItems, $products, $categories): void {
                $entries = [];
                if ($selected === 0) {
                    foreach ($selectedItems as $variant) {
                        $code = $variant["ProductCode"];
                        $entries[] = new MenuOption($variant["Name"] . "\n$" . $variant["Pricing"]["Price1-0"], new FormIcon("https://cache.dominos.com/olo/6_47_2/assets/build/market/US/_en/images/img/products/larges/$code.jpg"));
                    }
                    $entries[] = new MenuOption("Submit Order");

                    $viewCartForm = new MenuForm("My Cart", 'You currently have ' . count($selectedItems) . ' items selected.', $entries, function (): void {
                    }, function (Player $player) use ($categoryForm): void {
                        $player->sendForm($categoryForm);
                    });

                    $viewCartReflection = new ReflectionClass($viewCartForm);
                    $onSubmit = $viewCartReflection->getProperty('onSubmit');
                    $onSubmit->setAccessible(true);
                    $onSubmit->setValue($viewCartForm, function (Player $player, int $selected) use ($viewCartReflection, $categoryForm, $reflection, $viewCartForm, &$selectedItems): void {
                        if ($selected < count($selectedItems)) {
                            $variant = $selectedItems[$selected];
                            $name = $variant["Name"];
                            $price = $variant["Pricing"]["Price1-0"];
                            $player->sendForm(new MenuForm($name, "Are you sure you want to remove $name ($$price) to your cart?", [
                                new MenuOption("Yes!"),
                                new MenuOption("No."),
                            ], function (Player $player, int $choice) use ($selected, $viewCartReflection, $categoryForm, $reflection, &$selectedItems, $viewCartForm): void {
                                if ($choice === 0) {
                                    unset($selectedItems[$selected]);
                                    $selectedItems = array_values($selectedItems);

                                    $content = $reflection->getProperty('content');
                                    $content->setAccessible(true);
                                    $content->setValue($categoryForm, 'You currently have ' . count($selectedItems) . ' items selected.');

                                    $entries = [];
                                    foreach ($selectedItems as $variant) {
                                        $code = $variant["ProductCode"];
                                        $entries[] = new MenuOption($variant["Name"] . "\n$" . $variant["Pricing"]["Price1-0"], new FormIcon("https://cache.dominos.com/olo/6_47_2/assets/build/market/US/_en/images/img/products/larges/$code.jpg"));
                                    }
                                    $entries[] = new MenuOption("Submit Order");

                                    $content = $viewCartReflection->getProperty('content');
                                    $content->setAccessible(true);
                                    $content->setValue($viewCartForm, 'You currently have ' . count($selectedItems) . ' items selected.');

                                    $fields = $viewCartReflection->getProperty("options");
                                    $fields->setAccessible(true);
                                    $fields->setValue($viewCartForm, $entries);
                                }
                                $player->sendForm($viewCartForm);
                            }, function (Player $player) use ($viewCartForm): void {
                                $player->sendForm($viewCartForm);
                            }));
                            return;
                        }
                        if (count($selectedItems) == 0) {
                            $player->sendMessage(TextFormat::RED . "You need at least one item to order!");
                            return;
                        }
                        $player->sendMessage(TextFormat::GREEN . "Submitting your order to Domino's...");
                        Server::getInstance()->getAsyncPool()->submitTask(new AsyncOrderCreationTask($player->getName(), $this->store, $this->user, $selectedItems));
                    });

                    $player->sendForm($viewCartForm);
                    return;
                }

                $category = $categories[$selected - 1];
                $name = $category["Name"];
                if (strlen($name) === 0) {
                    // Seems to happen sometimes with things like chips.
                    $name = $category["Code"];
                }

                $internalEntries = [];
                foreach ($category["Products"] as $code) {
                    $internalEntries[] = $code;
                    $entries[] = new MenuOption($products[$code]["Name"], new FormIcon("https://cache.dominos.com/olo/6_47_2/assets/build/market/US/_en/images/img/products/larges/$code.jpg"));
                }

                foreach ($category['Categories'] as $subCategory) {
                    foreach ($subCategory["Products"] as $code) {
                        $internalEntries[] = $code;
                        $entries[] = new MenuOption($products[$code]["Name"], new FormIcon("https://cache.dominos.com/olo/6_47_2/assets/build/market/US/_en/images/img/products/larges/{$code}.jpg"));
                    }
                }

                $description = 'You currently have ' . count($selectedItems) . ' items selected.';
                if (count($entries) === 0) {
                    $description = 'No products found, try another category?';
                }

                $subCategoryForm = new MenuForm($name, $description, $entries, function (): void {
                }, function (Player $player) use ($categoryForm): void {
                    $player->sendForm($categoryForm);
                });

                $subCategoryReflection = new ReflectionClass($subCategoryForm);
                $onSubmit = $subCategoryReflection->getProperty('onSubmit');
                $onSubmit->setAccessible(true);
                $onSubmit->setValue($subCategoryForm, function (Player $player, int $selected) use ($categoryForm, $subCategoryForm, $variants, $internalEntries, &$selectedItems, $products, $categories, $reflection): void {
                    $product = $products[$internalEntries[$selected]];
                    $code = $product["Code"];

                    $internalEntries = [];
                    $entries = [];

                    foreach ($product["Variants"] as $variantCode) {
                        $variant = $variants[$variantCode];
                        $internalEntries[] = $variantCode;
                        $entries[] = new MenuOption($variant["Name"] . "\n$" . $variant["Pricing"]["Price1-0"], new FormIcon("https://cache.dominos.com/olo/6_47_2/assets/build/market/US/_en/images/img/products/larges/$code.jpg"));
                    }

                    $variantForm = new MenuForm($product["Name"] . " Variants", 'You currently have ' . count($selectedItems) . ' items selected.', $entries, function (Player $player, int $selected) use ($variants, $internalEntries): void {
                    }, function (Player $player) use ($subCategoryForm): void {
                        $player->sendForm($subCategoryForm);
                    });

                    $variantReflection = new ReflectionClass($variantForm);
                    $onSubmit = $variantReflection->getProperty('onSubmit');
                    $onSubmit->setAccessible(true);
                    $onSubmit->setValue($variantForm, function (Player $player, int $selected) use ($variantForm, &$selectedItems, $categoryForm, $variants, $internalEntries, $reflection): void {
                        $variant = $variants[$internalEntries[$selected]];
                        $name = $variant["Name"];
                        $price = $variant["Pricing"]["Price1-0"];

                        $player->sendForm(new MenuForm($name, "Are you sure you want to add $name ($$price) to your cart?", [
                            new MenuOption("Yes!"),
                            new MenuOption("No."),
                        ], function (Player $player, int $selected) use ($variantForm, &$selectedItems, $variant, $categoryForm, $reflection): void {
                            if ($selected === 0) {
                                $selectedItems[] = $variant;
                                $content = $reflection->getProperty('content');
                                $content->setAccessible(true);
                                $content->setValue($categoryForm, 'You currently have ' . count($selectedItems) . ' items selected.');
                                $player->sendForm($categoryForm);
                                return;
                            }
                            $player->sendForm($variantForm);
                        }, function (Player $player) use ($variantForm): void {
                            $player->sendForm($variantForm);
                        }));
                    });

                    $player->sendForm($variantForm);
                });

                $player->sendForm($subCategoryForm);
            });

            $player->sendForm($categoryForm);
        }
    }

}
